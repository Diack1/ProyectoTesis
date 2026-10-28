<?php
namespace Tests\Feature;

use App\Models\{Abono, Abonado, Espacio, Estadia, Pago, Reserva, User, VehiculoTipo, Tarifa};
use App\Services\{AbonoService, EstadiaService, LiquidacionService, ReubicacionService, ReservaDisponibilidadService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AbonadosTest extends TestCase {
    use RefreshDatabase;
    private User $staff;
    private VehiculoTipo $tipo;
    private Espacio $techo;
    private Espacio $patio;
    protected function setUp(): void {
        parent::setUp(); $this->travelTo(now()->setDate(2026,10,27)->setTime(10,0));
        $this->staff=User::factory()->create(['role'=>'operador']);
        $this->tipo=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        Tarifa::create(['vehiculo_tipo_id'=>$this->tipo->id,'nombre'=>'Hora','tipo_tarifa'=>'por_hora','monto_por_hora'=>5,'monto_por_fraccion'=>5,'minutos_fraccion'=>60,'tolerancia_minutos'=>5,'penalidad_por_fraccion'=>5,'activo'=>true]);
        $this->techo=$this->space('T01'); $this->patio=$this->space('T27');
    }
    private function space($code): Espacio {
        $s=Espacio::create(['codigo'=>$code,'activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']); $s->vehiculoTipos()->attach($this->tipo); return $s;
    }
    private function mensualidad(?Espacio $s=null,array $overrides=[]): Abono {
        return app(AbonoService::class)->registrar(array_replace(['nombre'=>'Cliente mensual','placas'=>['ABO123'],'espacio_id'=>($s??$this->techo)->id,'inicio'=>'2026-10-01','metodo_pago'=>'efectivo','efectivo_recibido'=>270,'pago_verificado'=>1],$overrides),$this->staff);
    }
    private function entrar(Espacio $space,array $overrides=[]): Estadia {
        return app(EstadiaService::class)->ingresar(array_replace(['espacio_id'=>$space->id,'vehiculo_tipo_id'=>$this->tipo->id,'placa'=>'TMP123','autoriza_movimiento'=>1,'llave_recibida'=>1,'ubicacion_llave'=>'Casillero 12'],$overrides),$this->staff);
    }
    public function test_monthly_prices_history_payment_and_overlapping_assignment(): void {
        $a=$this->mensualidad(); $p=$this->mensualidad($this->patio);
        $this->assertSame('270.00',$a->monto); $this->assertSame('240.00',$p->monto);
        $this->assertSame('aprobado',$a->pago->estado);
        $this->assertSame('2026-11-01',$a->fin->toDateString());
        try { $this->mensualidad(); $this->fail('Overlap accepted'); } catch(ValidationException $e) {}
        $this->assertSame(2,Abono::count()); $this->assertSame(2,Abonado::count());
        $b=$this->mensualidad(null,['abonado_id'=>$a->abonado_id,'inicio'=>'2026-11-01']);
        $this->assertSame(3,Abono::count()); $this->assertSame(2,Abonado::count());
        $this->assertSame($a->fin->toDateString(),$b->inicio->toDateString());
    }
    public function test_unverified_payment_rolls_back_customer_and_period(): void {
        try { $this->mensualidad(null,['pago_verificado'=>0]); $this->fail(); } catch(ValidationException $e) {}
        $this->assertSame(0,Abono::count()); $this->assertSame(0,Abonado::count()); $this->assertSame(0,Pago::count());
    }
    public function test_space_not_web_bookable_and_temporary_entry_requires_keys(): void {
        $a=$this->mensualidad();
        $this->assertFalse(app(ReservaDisponibilidadService::class)->estadoParaTarjeta($this->techo->fresh())['puede_reservar']);
        $this->actingAs(User::factory()->create())->post(route('reservas.store',$this->techo),['placa'=>'WEB123','vehiculo_tipo_id'=>$this->tipo->id,'duracion_minutos'=>60])->assertRedirect();
        $this->assertSame(0,Reserva::count());
        try { $this->entrar($this->techo,['llave_recibida'=>0]); $this->fail(); } catch(ValidationException $e) {}
        $stay=$this->entrar($this->techo);
        $this->assertNull($stay->abono_id);
        $this->assertSame('Casillero 12',$stay->ubicacion_llave);
    }
    public function test_relocation_preserves_billing_and_makes_room_for_subscriber(): void {
        $a=$this->mensualidad(); $stay=$this->entrar($this->techo);
        $snapshot=$stay->tarifa_snapshot; $start=$stay->inicio_cobro; $code=$stay->codigo_ticket;
        $this->travel(30)->minutes();
        $data=['origen_id'=>$this->techo->id,'destino_id'=>$this->patio->id,'motivo'=>'Llegada del abonado','movimiento_confirmado'=>1];
        app(ReubicacionService::class)->mover($stay,$data,$this->staff);
        $stay->refresh(); $this->assertSame($this->patio->id,$stay->espacio_id); $this->assertSame($code,$stay->codigo_ticket);
        $this->assertEquals($start,$stay->inicio_cobro); $this->assertSame($snapshot,$stay->tarifa_snapshot);
        $this->assertSame(1,$stay->reubicaciones()->count());
        $abo=$this->entrar($this->techo,['placa'=>'ABO123','abono_id'=>$a->id]);
        $this->assertSame(0,app(LiquidacionService::class)->calcular($abo,now()->addHours(2))['saldo_centavos']);
        try { app(ReubicacionService::class)->mover($stay,$data,$this->staff); $this->fail(); } catch(ValidationException $e) {}
        $this->assertSame(1,$stay->reubicaciones()->count());
    }
    public function test_occupied_destination_and_missing_authorization_leave_everything_intact(): void {
        $stay=$this->entrar($this->techo); $other=$this->entrar($this->patio,['placa'=>'OTH123']);
        try { app(ReubicacionService::class)->mover($stay,['origen_id'=>$this->techo->id,'destino_id'=>$this->patio->id,'motivo'=>'Prueba','movimiento_confirmado'=>1],$this->staff); $this->fail(); } catch(ValidationException $e) {}
        $this->assertSame($this->techo->id,$stay->fresh()->espacio_id); $this->assertSame(0,$stay->reubicaciones()->count());
    }
    public function test_subscriber_expiry_bills_hourly_and_contiguous_paid_renewal_extends_coverage(): void {
        $a=$this->mensualidad(); $stay=$this->entrar($this->techo,['placa'=>'ABO123','abono_id'=>$a->id]);
        $service=app(LiquidacionService::class);
        $this->assertSame(0,$service->calcular($stay,$a->fin)['saldo_centavos']);
        $this->assertSame(500,$service->calcular($stay,$a->fin->copy()->addSecond())['saldo_centavos']);
        $this->assertSame(1000,$service->calcular($stay,$a->fin->copy()->addMinutes(66))['saldo_centavos']);
        $this->mensualidad(null,['abonado_id'=>$a->abonado_id,'inicio'=>'2026-11-01']);
        $this->assertSame(0,$service->calcular($stay,$a->fin->copy()->addDays(5))['saldo_centavos']);
    }
    public function test_pages_and_signed_qr_reject_customers_and_scan_is_read_only(): void {
        $a=$this->mensualidad(); $url=URL::signedRoute('admin.abonados.qr',$a->abonado_id);
        $this->actingAs(User::factory()->create())->get(route('admin.abonados.index'))->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.abonados.index'))->assertOk();
        $this->get(route('admin.abonados.index',['buscar'=>'ABO123']))->assertOk()->assertSee('Cliente mensual');
        $this->get(route('admin.abonados.show',$a->abonado_id))->assertOk()->assertSee('data:image/svg+xml;base64,',false);
        $this->get($url)->assertRedirect(); $this->get($url.'&x=1')->assertForbidden();
        $this->get(route('admin.estadias.create'))->assertOk()->assertSee('Abono vigente');
        $this->assertSame(0,Estadia::count());
        $stay=$this->entrar($this->techo);
        $this->get(route('admin.estadias.show',$stay))->assertOk()->assertSee('Confirmar reubicación');
    }
    public function test_http_creation_renewal_and_duplicate_payment(): void {
        $this->actingAs($this->staff);
        $data=['nombre'=>'Cliente','placas_texto'=>'ABC-123, XYZ789','espacio_id'=>$this->techo->id,'inicio'=>'2026-10-01','metodo_pago'=>'yape','referencia_pago'=>'OPER123','pago_verificado'=>1];
        $this->post(route('admin.abonados.store'),$data)->assertSessionHasNoErrors()->assertRedirect();
        $a=Abono::firstOrFail();
        $this->assertSame(['ABC123','XYZ789'],$a->abonado->placas);
        $this->post(route('admin.abonados.store'),array_replace($data,['inicio'=>'2026-11-01','abonado_id'=>$a->abonado_id]))->assertSessionHasErrors();
        $this->post(route('admin.abonados.store'),['abonado_id'=>$a->abonado_id,'espacio_id'=>$this->techo->id,'inicio'=>'2026-11-01','metodo_pago'=>'efectivo','efectivo_recibido'=>270,'pago_verificado'=>1])->assertSessionHasNoErrors();
        $this->assertSame(2,Abono::count());
    }

    public function test_relocated_reservation_keeps_original_allocation_without_blocking_its_old_space(): void {
        $customer=User::factory()->create();
        $r=Reserva::create(['user_id'=>$customer->id,'espacio_id'=>$this->techo->id,'vehiculo_tipo_id'=>$this->tipo->id,
            'codigo_reserva'=>'RES-MOVE','placa'=>'TMP123','fecha_reserva'=>today(),'hora_inicio'=>'10:00:00','hora_fin'=>'11:00:00',
            'duracion_minutos'=>60,'monto_total'=>5,'tarifa_hora'=>5,'tarifa_nombre'=>'Hora','tipo_tarifa'=>'por_hora',
            'estado'=>'confirmada','tolerancia_llegada_minutos'=>15,'minutos_fraccion_snapshot'=>60,'tolerancia_minutos'=>5,'penalidad_por_fraccion'=>5]);
        Pago::create(['reserva_id'=>$r->id,'user_id'=>$customer->id,'codigo_pago'=>'PAY-MOVE','metodo_pago'=>'efectivo','monto'=>5,'estado'=>'aprobado']);
        $stay=$this->entrar($this->techo,['reserva_id'=>$r->id]);
        app(ReubicacionService::class)->mover($stay,['origen_id'=>$this->techo->id,'destino_id'=>$this->patio->id,'motivo'=>'Reorganización autorizada','movimiento_confirmado'=>1],$this->staff);
        $this->assertSame($this->techo->id,$r->fresh()->espacio_id);
        $this->assertTrue(app(ReservaDisponibilidadService::class)->estadoParaTarjeta($this->techo->fresh())['puede_reservar']);
        $stay->refresh();
        $this->travel(30)->minutes();
        $quote=Crypt::encryptString(json_encode(['id'=>$stay->id,'inicio'=>$stay->inicio_cobro->toIso8601String(),'corte'=>now()->toIso8601String()]));
        app(EstadiaService::class)->salir($stay,['cotizacion'=>$quote],$this->staff);
        $this->assertSame('finalizada',$r->fresh()->estado);
        $this->assertSame('libre',$this->patio->fresh()->estado_actual);
        $this->assertSame('0.00',$stay->fresh()->saldo_cobrado);
    }

    public function test_no_destination_and_expired_or_wrong_plate_abono_cannot_enter(): void {
        $a=$this->mensualidad();
        try { $this->entrar($this->techo,['abono_id'=>$a->id,'placa'=>'OTHER12']); $this->fail(); } catch(ValidationException $e) {}
        $stay=$this->entrar($this->techo);
        $this->entrar($this->patio,['placa'=>'OTHER12']);
        $this->actingAs($this->staff)->get(route('admin.estadias.show',$stay))->assertOk()->assertSee('No hay un espacio disponible para reubicar');
        $this->assertSame(0,$stay->reubicaciones()->count());
        $this->travelTo($a->fin->copy()->addMinute());
        $s=$this->space('T02');
        try { $this->entrar($s,['abono_id'=>$a->id,'placa'=>'ABO123']); $this->fail(); } catch(ValidationException $e) {}
        $this->assertSame(2,Estadia::count());
    }

    public function test_customer_cannot_create_monthly_payment_or_move_and_future_assignment_blocks_crossing_web_booking(): void {
        $a=$this->mensualidad(null,['inicio'=>'2026-11-01']);
        $client=User::factory()->create();
        $this->actingAs($client)->post(route('admin.abonados.store'),[])->assertForbidden();
        $stay=$this->entrar($this->patio);
        $this->post(route('admin.estadias.reubicar',$stay),[])->assertForbidden();
        $this->travelTo($a->inicio->copy()->subMinutes(30));
        $this->post(route('reservas.store',$this->techo),['placa'=>'WEB123','vehiculo_tipo_id'=>$this->tipo->id,'duracion_minutos'=>60])->assertStatus(409);
        $this->assertSame(0,Reserva::count());
    }

    public function test_late_renewal_only_charges_gap_and_departure_requires_actual_payment(): void {
        $a=$this->mensualidad(); $stay=$this->entrar($this->techo,['placa'=>'ABO123','abono_id'=>$a->id]);
        $this->travelTo($a->fin->copy()->addDay());
        $this->mensualidad(null,['abonado_id'=>$a->abonado_id,'inicio'=>'2026-11-02']);
        $this->travel(2)->days();
        $quote=Crypt::encryptString(json_encode(['id'=>$stay->id,'inicio'=>$stay->inicio_cobro->toIso8601String(),'corte'=>now()->toIso8601String()]));
        $bill=app(LiquidacionService::class)->calcular($stay,now());
        $this->assertSame(12000,$bill['saldo_centavos']);
        try { app(EstadiaService::class)->salir($stay,['cotizacion'=>$quote],$this->staff); $this->fail(); } catch(ValidationException $e) {}
        $this->assertNull($stay->fresh()->hora_salida);
        app(EstadiaService::class)->salir($stay,['cotizacion'=>$quote,'pago_verificado'=>1,'metodo_pago'=>'efectivo','efectivo_recibido'=>120],$this->staff);
        $this->assertSame('120.00',$stay->fresh()->saldo_cobrado);
    }
}
