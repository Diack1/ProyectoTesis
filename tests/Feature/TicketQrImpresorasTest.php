<?php

namespace Tests\Feature;

use App\Models\{CajaApertura, Espacio, Estadia, Impresora, Tarifa, User, VehiculoTipo};
use App\Services\TicketQrService;
use App\Services\ImpresionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class TicketQrImpresorasTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_creates_one_active_ticket_and_signed_qr_opens_its_space(): void
    {
        $operator = User::factory()->create(['role'=>'operador']);
        CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$operator->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $tipo = VehiculoTipo::create(['nombre'=>'Automóvil','codigo'=>'auto','activo'=>true]);
        $espacio = Espacio::create(['codigo'=>'E01','estado_actual'=>'libre','activo'=>true,'modo_monitoreo'=>'manual']);
        $espacio->vehiculoTipos()->attach($tipo);
        $tarifa = Tarifa::create(['vehiculo_tipo_id'=>$tipo->id,'nombre'=>'Regular','tipo_tarifa'=>'por_hora','monto_por_hora'=>5,'monto_por_fraccion'=>2,'minutos_fraccion'=>30,'tiempo_minimo_minutos'=>60,'tolerancia_minutos'=>10,'penalidad_por_fraccion'=>3,'activo'=>true,'prioridad'=>1]);
        $this->actingAs($operator);
        $payload = ['placa'=>'ABC-123','espacio_id'=>$espacio->id,'vehiculo_tipo_id'=>$tipo->id,'tarifa_id'=>$tarifa->id];
        $this->post(route('admin.estadias.store'),$payload)->assertRedirect(route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'ticket','nuevo'=>1]))->assertSessionHasNoErrors();
        $ticket = Estadia::firstOrFail();
        $this->assertNull($ticket->hora_salida);
        $this->assertNotEmpty($ticket->codigo_ticket);
        $this->get(route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'ticket','nuevo'=>1]))->assertOk()
            ->assertSee('Imprimir ticket')->assertSee('Ver ticket')->assertSee('Cobrar')->assertSee($ticket->codigo_ticket)
            ->assertDontSee('ticket-entry-modal');
        $path = app(TicketQrService::class)->path($ticket);
        $this->getJson($path)->assertOk()->assertJsonPath('estado','activo')
            ->assertJsonPath('url',route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'ticket']));
        $this->getJson($path.'&codigo=falso')->assertForbidden();
        $this->post(route('admin.impresoras.estadia',$ticket))->assertRedirect(route('admin.estadias.ticket',[$ticket,'print'=>1,'papel'=>80]));
        $this->postJson(route('admin.impresoras.estadia',$ticket))->assertStatus(422)->assertJsonMissing(['message'=>'Impresión realizada']);
        Impresora::create(['nombre'=>'Red','tipo'=>'termica','conexion'=>'red','direccion_ip'=>'192.168.1.25','puerto'=>9100,'papel_mm'=>80,'predeterminada_unica'=>1]);
        $printerService = Mockery::mock(ImpresionService::class);
        $printerService->shouldReceive('ticket')->once()->andReturn('TICKET');
        $printerService->shouldReceive('enviar')->once()->andReturnNull();
        $this->app->instance(ImpresionService::class,$printerService);
        $this->postJson(route('admin.impresoras.estadia',$ticket))->assertOk()->assertJsonPath('message','Impresión realizada');
        $printerService = Mockery::mock(ImpresionService::class);
        $printerService->shouldReceive('ticket')->once()->andReturn('TICKET');
        $printerService->shouldReceive('enviar')->once()->andThrow(ValidationException::withMessages(['impresora'=>'Sin conexión']));
        $this->app->instance(ImpresionService::class,$printerService);
        $this->postJson(route('admin.impresoras.estadia',$ticket))->assertStatus(422)->assertJsonPath('message','Sin conexión');
        $this->post(route('admin.estadias.store'),$payload)->assertSessionHasErrors();
        $this->assertSame(1,Estadia::count());
        $ticket->update(['hora_salida'=>now(),'placa_activa'=>null,'espacio_activo_id'=>null]);
        $this->getJson($path)->assertOk()->assertJsonPath('estado','finalizado');
        $this->get(route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'ticket','ticket'=>$ticket->id]))
            ->assertOk()->assertSee('Ticket finalizado')->assertDontSee('>Cobrar</a>',false);
    }

    public function test_printer_profiles_require_admin_and_private_network_address(): void
    {
        $operator = User::factory()->create(['role'=>'operador']);
        $owner = User::factory()->create(['role'=>'super_admin']);
        $data = ['nombre'=>'Térmica recepción','tipo'=>'termica','conexion'=>'red','direccion_ip'=>'192.168.1.25','puerto'=>9100,'papel_mm'=>80];
        $this->actingAs($operator)->get(route('admin.impresoras.index'))->assertForbidden();
        $this->post(route('admin.impresoras.store'),$data)->assertForbidden();
        $this->actingAs($owner)->post(route('admin.impresoras.store'),array_merge($data,['direccion_ip'=>'8.8.8.8']))->assertSessionHasErrors('direccion_ip');
        $this->post(route('admin.impresoras.store'),$data)->assertRedirect(route('admin.impresoras.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('impresoras',['nombre'=>'Térmica recepción','predeterminada_unica'=>1]);
        $printer = Impresora::firstOrFail();
        $this->put(route('admin.impresoras.update',$printer),array_merge($data,['nombre'=>'Caja 01','papel_mm'=>58]))->assertSessionHasNoErrors();
        $this->assertSame(58,$printer->fresh()->papel_mm);
    }
}
