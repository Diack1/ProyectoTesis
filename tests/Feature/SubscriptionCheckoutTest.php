<?php

namespace Tests\Feature;

use App\Models\{Abonado, Abono, CajaCierre, Espacio, Pago, Tarifa, User, VehiculoTipo};
use App\Services\{EstadiaService, LiquidacionService, TarifaService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_months_from_form_are_cast_and_end_dates_do_not_overflow(): void
    {
        $this->travelTo(Carbon::parse('2027-01-31 10:00:00'));
        $staff=User::factory()->create(['role'=>'operador']);
        $type=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        $space=Espacio::create(['codigo'=>'T27','activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $rate=Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Mensual','tipo_tarifa'=>'mensual',
            'monto_base'=>100,'monto_por_hora'=>0,'activo'=>true]);
        $this->actingAs($staff);
        $form=['nombre'=>'Ana Rojas','telefono'=>'987654321','dni'=>'12345678',
            'placas'=>['ABC123'],'espacio_id'=>$space->id,'tarifa_id'=>$rate->id,
            'inicio'=>'2027-01-31'];

        foreach ([1=>'2027-02-28', 2=>'2027-03-31', 3=>'2027-04-30', 4=>'2027-05-31'] as $months=>$end) {
            $this->post(route('admin.abonados.preparar'),$form+['meses'=>(string)$months])
                ->assertSessionHasNoErrors()->assertRedirect(route('admin.abonados.cobro'));
            $draft=session('abono_cobro_draft');
            $this->assertSame($months,$draft['meses']);
            $this->assertSame($end,$draft['fin']);
            $this->assertSame(10000*$months,$draft['monto_base_centavos']);
            $this->get(route('admin.abonados.cobro'))->assertOk()->assertSee('Cobro del abono');
        }
        $this->assertSame(0,Abonado::count());
        $this->assertSame(0,Abono::count());
        $this->assertSame(0,Pago::count());

        foreach (['0','5','1.5','abc'] as $invalid) {
            $this->post(route('admin.abonados.preparar'),$form+['meses'=>$invalid])
                ->assertRedirect()->assertSessionHasErrors('meses')
                ->assertSessionHasInput('nombre','Ana Rojas')
                ->assertSessionHasInput('meses',$invalid);
        }
        $this->get(route('admin.abonados.create'))->assertOk()
            ->assertSee('Revisa estos datos:')
            ->assertSee('Selecciona una cantidad de meses válida (1, 2, 3 o 4).')
            ->assertSee('Ana Rojas');
        $this->assertSame(0,Abono::count());
    }

    public function test_hour_block_and_monthly_tariffs_use_shared_excess_rules(): void
    {
        $owner=User::factory()->create(['role'=>'super_admin']);
        $type=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        $this->actingAs($owner);
        $this->post(route('admin.tarifas.store'),['vehiculo_tipo_id'=>$type->id,'tipo_tarifa'=>'por_hora',
            'precio'=>5,'minutos_fraccion'=>15,'monto_por_fraccion'=>1,'tolerancia_minutos'=>10,'activo'=>1])->assertSessionHasNoErrors();
        $this->post(route('admin.tarifas.store'),['vehiculo_tipo_id'=>$type->id,'tipo_tarifa'=>'bloque_horas',
            'horas_bloque'=>12,'precio'=>14,'activo'=>1])->assertSessionHasNoErrors();
        $this->post(route('admin.tarifas.store'),['vehiculo_tipo_id'=>$type->id,'tipo_tarifa'=>'mensual',
            'precio'=>100,'activo'=>1])->assertSessionHasNoErrors();
        $tarifas=app(TarifaService::class);
        $this->assertSame(5.0,$tarifas->calcularMonto($type,now(),60)['monto_total']);
        $this->assertSame(5.0,$tarifas->calcularMonto($type,now(),70)['monto_total']);
        $this->assertSame(6.0,$tarifas->calcularMonto($type,now(),71)['monto_total']);
        $this->assertSame(14.0,$tarifas->calcularMonto($type,now(),720)['monto_total']);
        $this->assertSame(15.0,$tarifas->calcularMonto($type,now(),731)['monto_total']);
        $this->assertSame(100.0,(float)Tarifa::where('tipo_tarifa','mensual')->sole()->monto_base);
        $this->assertSame(3,Tarifa::count());
        $space=Espacio::create(['codigo'=>'T27','activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $stay=app(EstadiaService::class)->ingresar(['placa'=>'ABC123','espacio_id'=>$space->id,
            'vehiculo_tipo_id'=>$type->id,
            'tarifa_id'=>Tarifa::where('vehiculo_tipo_id',$type->id)->where('tipo_tarifa','bloque_horas')->value('id')],$owner);
        $this->travel(12)->hours();
        $this->assertSame(1400,app(LiquidacionService::class)->calcular($stay,now())['saldo_centavos']);
        $this->travel(11)->minutes();
        $this->assertSame(1500,app(LiquidacionService::class)->calcular($stay,now())['saldo_centavos']);
    }

    public function test_abono_is_created_only_after_complete_mixed_payment_and_cash_change_is_not_income(): void
    {
        $staff=User::factory()->create(['role'=>'operador']);
        $this->actingAs($staff)->post(route('admin.caja.abrir'),['monto_inicial'=>0])->assertSessionHasNoErrors();
        $type=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        $space=Espacio::create(['codigo'=>'T27','activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $rate=Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Auto · mensual','tipo_tarifa'=>'mensual',
            'monto_base'=>100,'monto_por_hora'=>0,'activo'=>true]);
        $this->actingAs($staff)->get(route('admin.abonados.create'))->assertOk()->assertSee('Registrar y pagar');
        $this->post(route('admin.abonados.preparar'),['nombre'=>'Juan Pérez','telefono'=>'987654321',
            'dni'=>'12345678','placas'=>['ABC123','XYZ789'],'espacio_id'=>$space->id,'tarifa_id'=>$rate->id,
            'meses'=>1,'inicio'=>now()->toDateString()])->assertRedirect(route('admin.abonados.cobro'));
        $this->assertSame(0,Abonado::count()); $this->assertSame(0,Abono::count()); $this->assertSame(0,Pago::count());
        $this->get(route('admin.abonados.cobro'))->assertOk()->assertSee('Cobro del abono')->assertSee('Juan Pérez');
        $token=session('abono_cobro_draft.token');
        $this->post(route('admin.abonados.store'),['draft_token'=>$token,'tipo_comprobante'=>'boleta',
            'tipo_impresion'=>'detallado','pago_verificado'=>1,
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>50,'efectivo_recibido'=>60],
                ['metodo_pago'=>'tarjeta','monto'=>50]]])->assertSessionHasNoErrors()->assertRedirect();
        $abono=Abono::with('pagos')->sole();
        $this->assertSame('100.00',$abono->monto);
        $this->assertSame('10.00',$abono->vuelto);
        $this->assertSame(2,$abono->pagos->count());
        $this->assertSame(50.0,(float)$abono->pagos->where('metodo_pago','efectivo')->sum('monto'));
        $this->assertSame('12345678',$abono->abonado->dni);
        $this->assertSame(0,session('abono_cobro_draft') ? 1 : 0);
        $this->get(route('admin.abonados.pagado',$abono))->assertOk()->assertSee('Comprobante de pago')
            ->assertSee('Imprimir')->assertSee('Continuar vendiendo')->assertDontSee('Confirmar definitivamente');
        $this->get(route('admin.abonados.comprobante',$abono))->assertOk()->assertSee('Vuelto');
        $this->get(route('admin.caja.index'))->assertOk()->assertSee('Abono #'.$abono->id);
        $this->post(route('admin.caja.cerrar'),['efectivo_contado'=>50])->assertSessionHasNoErrors();
        $cierre=CajaCierre::sole();
        $this->assertSame(50.0,(float)$cierre->efectivo_esperado);
        $this->assertSame(50.0,(float)$cierre->totales_por_medio['tarjeta']);
        $this->post(route('admin.caja.abrir'),['monto_inicial'=>0])->assertSessionHasNoErrors();
        $this->post(route('admin.abonados.preparar'),['abonado_id'=>$abono->abonado_id,
            'espacio_id'=>$space->id,'tarifa_id'=>$rate->id,'meses'=>1,
            'inicio'=>$abono->fin->toDateString()])->assertSessionHasNoErrors();
        $renewalToken=session('abono_cobro_draft.token');
        $this->post(route('admin.abonados.store'),['draft_token'=>$renewalToken,'tipo_comprobante'=>'boleta',
            'tipo_impresion'=>'detallado','pago_verificado'=>1,
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>100,'efectivo_recibido'=>100]]])->assertSessionHasNoErrors();
        $this->assertSame(2,Abono::count());
        $this->assertSame(3,Pago::count());
    }

    public function test_partial_payment_and_cancellation_do_not_activate_subscription(): void
    {
        $staff=User::factory()->create(['role'=>'operador']);
        $this->actingAs($staff)->post(route('admin.caja.abrir'),['monto_inicial'=>0])->assertSessionHasNoErrors();
        $type=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        $space=Espacio::create(['codigo'=>'T27','activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $rate=Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Mensual','tipo_tarifa'=>'mensual',
            'monto_base'=>100,'monto_por_hora'=>0,'activo'=>true]);
        $this->actingAs($staff)->post(route('admin.abonados.preparar'),['nombre'=>'Ana Rojas','telefono'=>'987654321',
            'dni'=>'12345678','placas'=>['ABC123'],'espacio_id'=>$space->id,'tarifa_id'=>$rate->id,
            'meses'=>1,'inicio'=>now()->toDateString()])->assertSessionHasNoErrors();
        $token=session('abono_cobro_draft.token');
        $this->post(route('admin.abonados.store'),['draft_token'=>$token,'tipo_comprobante'=>'boleta',
            'tipo_impresion'=>'consumo','pago_verificado'=>1,
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>50,'efectivo_recibido'=>50]]])->assertSessionHasErrors();
        $this->assertSame(0,Abono::count()); $this->assertSame(0,Pago::count());
        $this->post(route('admin.abonados.cobro.cancelar'))->assertRedirect(route('admin.abonados.create'));
        $this->assertNull(session('abono_cobro_draft'));
        $this->get(route('admin.abonados.create'))->assertOk();
        $this->assertSame(0,Abonado::count());
    }

    public function test_three_months_use_monthly_rate_and_discount_before_payment(): void
    {
        $staff=User::factory()->create(['role'=>'operador']);
        $this->actingAs($staff)->post(route('admin.caja.abrir'),['monto_inicial'=>0])->assertSessionHasNoErrors();
        $type=VehiculoTipo::create(['codigo'=>'auto','nombre'=>'Auto','activo'=>true]);
        $space=Espacio::create(['codigo'=>'T27','activo'=>true,'estado_actual'=>'libre','modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $rate=Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Mensual','tipo_tarifa'=>'mensual',
            'monto_base'=>100,'monto_por_hora'=>0,'activo'=>true]);
        $this->actingAs($staff)->post(route('admin.abonados.preparar'),['nombre'=>'Ana Rojas','telefono'=>'987654321',
            'dni'=>'12345678','placas'=>['ABC123'],'espacio_id'=>$space->id,'tarifa_id'=>$rate->id,
            'meses'=>3,'inicio'=>now()->toDateString()])->assertSessionHasNoErrors();
        $this->get(route('admin.abonados.cobro'))->assertOk()->assertSee('data-base="30000"',false);
        $token=session('abono_cobro_draft.token');
        $this->post(route('admin.abonados.store'),['draft_token'=>$token,'tipo_comprobante'=>'boleta',
            'tipo_impresion'=>'consumo','pago_verificado'=>1,'descuento_porcentaje'=>10,
            'motivo_descuento'=>'Promoción de prueba',
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>100,'efectivo_recibido'=>100],
                ['metodo_pago'=>'tarjeta','monto'=>170]]])->assertSessionHasNoErrors();
        $abono=Abono::sole();
        $this->assertSame(3,$abono->meses);
        $this->assertSame('300.00',$abono->monto_base);
        $this->assertSame('270.00',$abono->monto);
        $this->assertSame(now()->addMonthsNoOverflow(3)->toDateString(),$abono->fin->toDateString());
        $this->get(route('admin.abonados.index',['estado'=>'al_dia']))->assertOk()->assertSee('Ana Rojas');
        $this->get(route('admin.abonados.index',['estado'=>'vencidos']))->assertOk()->assertDontSee('Ana Rojas');
    }
}
