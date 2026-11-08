<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\EstadiaAuditoria;
use App\Models\Tarifa;
use App\Models\User;
use App\Models\VehiculoTipo;
use App\Services\EstadiaService;
use App\Services\LiquidacionService;
use App\Services\ImpresionService;
use App\Models\Impresora;
use App\Models\Venta;
use App\Models\Pago;
use Mockery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspacioOperacionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private Espacio $source;
    private Espacio $target;
    private VehiculoTipo $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'operador']);
        \App\Models\CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$this->staff->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $this->type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        Tarifa::create(['vehiculo_tipo_id' => $this->type->id, 'nombre' => 'Hora auto',
            'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'activo' => true]);
        $this->source = Espacio::create(['codigo' => 'T27', 'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual']);
        $this->target = Espacio::create(['codigo' => 'T28', 'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual']);
        $this->source->vehiculoTipos()->attach($this->type);
        $this->target->vehiculoTipos()->attach($this->type);
    }

    private function stay(): Estadia
    {
        return app(EstadiaService::class)->ingresar(['placa' => 'HUA123', 'espacio_id' => $this->source->id,
            'tarifa_id' => Tarifa::where('vehiculo_tipo_id',$this->type->id)->where('tipo_tarifa','por_hora')->value('id'),
            'vehiculo_tipo_id' => $this->type->id, 'autoriza_movimiento' => 1, 'llave_recibida' => 1,
            'ubicacion_llave' => 'Casillero 2'], $this->staff);
    }

    public function test_map_opens_free_space_with_entry_and_occupied_space_with_ticket(): void
    {
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.espacios.operar', $this->source), false)
            ->assertDontSee('id="summary-title"', false);
        $this->get(route('admin.espacios.operar', $this->source))->assertOk()->assertSee('Registrar ingreso en T27')->assertDontSee('Cobro de salida');
        $stay = $this->stay();
        $ticketPage = $this->get(route('admin.espacios.operar', $this->source))->assertOk()
            ->assertSee('Imprimir ticket')->assertSee('Ver ticket')->assertSee('Cobrar')->assertDontSee('Pagos agregados')
            ->assertDontSee('ticket-entry-modal');
        $this->assertSame(1,substr_count($ticketPage->getContent(),'class="entry-ticket-card"'));
        $this->get(route('admin.espacios.operar', $this->source))->assertDontSee('sidebar-quick-actions')->assertDontSee('space-context card');
        $this->get(route('admin.espacios.operar', ['espacio' => $this->source, 'vista' => 'ticket']))
            ->assertOk()->assertSee($stay->codigo_ticket)->assertDontSee('Pagos agregados');
        $this->get(route('admin.espacios.operar', ['espacio'=>$this->source,'vista'=>'ticket','detalle'=>1]))
            ->assertOk()->assertSee('ticket-detail-frame')->assertSee('incrustado=1')->assertDontSee('class="entry-ticket-card"',false);
        $this->get(route('admin.estadias.ticket',[$stay,'incrustado'=>1]))->assertOk()->assertDontSee('window.print()');
        $this->get(route('admin.espacios.operar', ['espacio'=>$this->source,'vista'=>'cobrar']))
            ->assertOk()->assertSee('Cobro de salida')->assertSee('Pagos agregados');
        $this->get(route('admin.espacios.operar', $this->source))->assertSee('Volver al mapa');
    }

    public function test_editing_type_and_rate_recalculates_checkout_without_changing_entry_time(): void
    {
        $stay = $this->stay();
        $entry = $stay->hora_ingreso->toIso8601String();
        $newType = VehiculoTipo::create(['nombre' => 'Camioneta', 'codigo' => 'camioneta', 'activo' => true]);
        $this->source->vehiculoTipos()->attach($newType);
        $rate = Tarifa::create(['vehiculo_tipo_id' => $newType->id, 'nombre' => 'Hora camioneta',
            'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 10, 'activo' => true]);
        $this->actingAs($this->staff)->get(route('admin.espacios.operar', ['espacio' => $this->source, 'vista' => 'editar']))
            ->assertOk()->assertSee('Tarifa seleccionada')->assertDontSee('name="salida_prevista"', false);
        $this->post(route('admin.estadias.corregir', $stay), ['placa' => 'HUA123',
            'vehiculo_tipo_id' => $newType->id, 'tarifa_id' => $rate->id,
            'motivo' => 'Tipo registrado incorrectamente', 'correccion_confirmada' => 1,
            'salida_prevista' => now()->addHours(8)->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $updated = $stay->fresh();
        $this->assertSame($entry, $updated->hora_ingreso->toIso8601String());
        $this->assertNull($updated->salida_prevista);
        $this->assertSame($newType->id, $updated->vehiculo_tipo_id);
        $this->assertSame('Hora camioneta', $updated->tarifa_snapshot['nombre']);
        $this->assertSame(1000, app(LiquidacionService::class)->calcular($updated, now())['saldo_centavos']);
        $this->assertSame('Auto', EstadiaAuditoria::sole()->valores_anteriores['tipo_vehiculo']);
        $this->assertSame('Camioneta', EstadiaAuditoria::sole()->valores_nuevos['tipo_vehiculo']);
    }

    public function test_correction_requires_reason_confirmation_and_records_before_after_with_user(): void
    {
        $stay = $this->stay();
        $this->actingAs($this->staff);
        $route = route('admin.estadias.corregir', $stay);
        $this->post($route, ['placa' => 'NEW123'])->assertSessionHasErrors(['motivo', 'correccion_confirmada']);
        $this->assertSame('HUA123', $stay->fresh()->placa);
        $this->post($route, ['placa' => 'NEW123', 'motivo' => 'Placa mal escrita', 'correccion_confirmada' => 1])
            ->assertRedirect(route('admin.espacios.operar', ['espacio' => $this->source, 'vista' => 'historial']));
        $this->assertSame('NEW123', $stay->fresh()->placa_activa);
        $audit = EstadiaAuditoria::sole();
        $this->assertSame($this->staff->id, $audit->usuario_id);
        $this->assertSame('HUA123', $audit->valores_anteriores['placa']);
        $this->assertSame('NEW123', $audit->valores_nuevos['placa']);
        $this->assertSame('Placa mal escrita', $audit->motivo);
        $this->get(route('admin.espacios.operar', ['espacio' => $this->source, 'vista' => 'historial']))
            ->assertOk()->assertSee('HUA123')->assertSee('NEW123')->assertSee('Placa mal escrita');
    }

    public function test_move_selects_compatible_destination_and_keeps_ticket_and_time(): void
    {
        $stay = $this->stay();
        $started = $stay->inicio_cobro;
        $this->actingAs($this->staff)->get(route('admin.espacios.operar', ['espacio' => $this->source, 'vista' => 'mover']))
            ->assertOk()->assertSee('T28')->assertSee('name="destino_id"', false);
        $this->post(route('admin.estadias.reubicar', $stay), ['origen_id' => $this->source->id,
            'destino_id' => $this->target->id, 'motivo' => 'Cambio autorizado',
            'movimiento_confirmado' => 1, 'desde_mapa' => 1])
            ->assertRedirect(route('admin.espacios.operar', ['espacio' => $this->target, 'vista' => 'historial']));
        $this->assertSame($this->target->id, $stay->fresh()->espacio_id);
        $this->assertTrue($started->equalTo($stay->fresh()->inicio_cobro));
        $this->assertSame(1, $stay->reubicaciones()->count());
        $this->get(route('admin.espacios.operar', $this->target))->assertOk()->assertSee('entry-ticket-card')->assertDontSee('ticket-entry-modal');
    }

    public function test_checkout_from_map_returns_to_contextual_receipt_and_history(): void
    {
        $stay = $this->stay();
        $quote = Crypt::encryptString(json_encode(['id' => $stay->id,
            'inicio' => $stay->inicio_cobro->toIso8601String(), 'corte' => now()->toIso8601String()]));
        $this->actingAs($this->staff)->post(route('admin.estadias.salida', $stay), [
            'cotizacion' => $quote, 'desde_mapa' => 1, 'pago_verificado' => 1,
            'pagos' => [['metodo_pago' => 'efectivo', 'monto' => 5, 'efectivo_recibido' => 10]],
        ])->assertRedirect(route('admin.espacios.operar', ['espacio'=>$this->source,'vista'=>'comprobante','ticket'=>$stay->id]));
        $this->assertDatabaseHas('ventas',['origen_tipo'=>'estadia','origen_id'=>$stay->id,'total'=>5]);
        $this->get(route('admin.espacios.operar', ['espacio'=>$this->source,'vista'=>'comprobante','ticket'=>$stay->id]))
            ->assertOk()->assertSee('Comprobante de pago')->assertSee('Imprimir')->assertSee('Continuar vendiendo')
            ->assertDontSee('Editar cobro')->assertDontSee('Confirmar definitivamente')->assertSee('S/ 5.00');
        $this->get(route('admin.espacios.operar', ['espacio'=>$this->source,'vista'=>'historial','ticket'=>$stay->id]))
            ->assertOk()->assertSee('Salida y cobro');
    }

    public function test_print_failure_does_not_undo_payment_and_manual_retry_does_not_duplicate_sale(): void
    {
        $stay=$this->stay();
        Impresora::create(['nombre'=>'Recepción','tipo'=>'termica','conexion'=>'red','direccion_ip'=>'192.168.1.25',
            'puerto'=>9100,'papel_mm'=>80,'predeterminada_unica'=>1]);
        $printer=Mockery::mock(ImpresionService::class);
        $printer->shouldReceive('comprobanteAutomatico')->once()->andReturn(['ok'=>false,'mensaje'=>'Sin papel']);
        $printer->shouldReceive('ticket')->once()->andReturn('COMPROBANTE');
        $printer->shouldReceive('enviar')->once()->andReturnNull();
        $this->app->instance(ImpresionService::class,$printer);
        $quote=Crypt::encryptString(json_encode(['id'=>$stay->id,'inicio'=>$stay->inicio_cobro->toIso8601String(),
            'corte'=>now()->toIso8601String()]));
        $this->actingAs($this->staff)->post(route('admin.estadias.salida',$stay),[
            'cotizacion'=>$quote,'pago_verificado'=>1,
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>5,'efectivo_recibido'=>5]],
        ])->assertRedirect(route('admin.espacios.operar',['espacio'=>$this->source,'vista'=>'comprobante','ticket'=>$stay->id]))
            ->assertSessionHas('impresion_automatica.ok',false);
        $this->assertSame(1,Venta::count());
        $this->assertSame(1,Pago::where('estadia_id',$stay->id)->count());
        $this->get(route('admin.espacios.operar',['espacio'=>$this->source,'vista'=>'comprobante','ticket'=>$stay->id]))
            ->assertOk()->assertSee('Sin papel')->assertSee('Imprimir');
        $this->postJson(route('admin.impresoras.estadia',$stay))->assertOk()->assertJsonPath('message','Impresión realizada');
        $this->assertSame(1,Venta::count());
        $this->assertSame(1,Pago::where('estadia_id',$stay->id)->count());
    }
}
