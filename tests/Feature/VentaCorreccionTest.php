<?php

namespace Tests\Feature;

use App\Models\{CajaApertura, CajaCierre, CajaMovimiento, Espacio, Pago, Reserva, User, Venta};
use App\Services\CajaService;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentaCorreccionTest extends TestCase
{
    use RefreshDatabase;

    private function venta(CajaApertura $caja): Venta
    {
        $espacio = Espacio::create(['codigo'=>'E99','estado_actual'=>'libre','activo'=>true]);
        $reserva = Reserva::create(['user_id'=>$caja->operador_id,'espacio_id'=>$espacio->id,
            'codigo_reserva'=>'RES-TEST-99','fecha_reserva'=>today(),'hora_inicio'=>'10:00:00','hora_fin'=>'11:00:00',
            'estado'=>'confirmada','monto_total'=>100]);
        $venta = Venta::create(['origen_tipo'=>'reserva', 'origen_id'=>$reserva->id, 'version'=>1,
            'numero'=>'RSV-000099', 'fecha'=>now(), 'cliente'=>'Cliente prueba',
            'tipo_comprobante'=>'boleta', 'tipo_impresion'=>'detallado', 'concepto'=>'ABC123',
            'importe_base'=>100, 'descuento'=>0, 'total'=>100, 'datos'=>['placa'=>'ABC123']]);
        Pago::create(['venta_id'=>$venta->id, 'reserva_id'=>$reserva->id, 'caja_apertura_id'=>$caja->id, 'codigo_pago'=>'ORIGINAL',
            'metodo_pago'=>'efectivo', 'monto'=>100, 'efectivo_recibido'=>100, 'vuelto'=>0,
            'estado'=>'aprobado', 'pagado_at'=>now()]);
        return $venta;
    }

    public function test_payment_change_keeps_receipt_and_reallocates_cash_methods(): void
    {
        $admin = User::factory()->create(['role'=>'super_admin']);
        $caja = CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$admin->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $venta = $this->venta($caja);
        $payload = ['tipo'=>'pagos','motivo'=>'Cliente pagó con dos medios', 'idempotencia'=>(string) Str::uuid(),
            'pagos'=>[['metodo_pago'=>'efectivo','monto'=>'40.00','efectivo_recibido'=>'40.00'],
                ['metodo_pago'=>'yape','monto'=>'60.00']]];
        $this->actingAs($admin)->post(route('admin.caja.ventas.update',$venta), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('corregida', $venta->fresh()->estado);
        $this->assertSame(1, Venta::count());
        $this->assertSame(1, $venta->correcciones()->count());
        $this->assertSame(['efectivo'=>4000,'yape'=>6000], app(CajaService::class)->arqueo($caja)['ventas']);
        $this->get(route('admin.caja.ventas.show',$venta))->assertOk()
            ->assertSee('Pago en Efectivo')->assertSee('Pago en Yape')
            ->assertSee('Cliente pagó con dos medios')->assertDontSee('caja_apertura_id');
        $this->post(route('admin.caja.ventas.update',$venta), $payload)->assertSessionHasErrors('idempotencia');
        $this->assertSame(1, $venta->correcciones()->count());
    }

    public function test_correction_after_closed_cash_keeps_closure_and_books_reversal_in_active_cash(): void
    {
        $admin = User::factory()->create(['role'=>'super_admin']);
        $old = CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$admin->id,'inicio_at'=>now()->subDay(),'abierta_unica'=>null]);
        $venta = $this->venta($old);
        Pago::where('venta_id',$venta->id)->update(['pagado_at'=>now()->subDay()]);
        $cierre = CajaCierre::create(['caja_apertura_id'=>$old->id, 'inicio_at'=>$old->inicio_at,
            'fin_at'=>now()->subHour(), 'operador_id'=>$admin->id, 'monto_inicial'=>0,
            'efectivo_esperado'=>100, 'efectivo_contado'=>100, 'diferencia'=>0,
            'ingresos_manuales'=>0, 'egresos_manuales'=>0, 'totales_por_medio'=>['efectivo'=>100],
            'pagos_count'=>1, 'reembolsos_count'=>0]);
        Pago::where('venta_id',$venta->id)->update(['caja_cierre_id'=>$cierre->id]);
        $current = CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>100,'operador_id'=>$admin->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $this->actingAs($admin)->post(route('admin.caja.ventas.update',$venta), [
            'tipo'=>'venta','motivo'=>'Corrección del importe cobrado','idempotencia'=>(string) Str::uuid(),
            'importe_base'=>'120.00','total'=>'120.00','descuento_porcentaje'=>'0','descuento_monto'=>'0',
            'tipo_comprobante'=>'boleta','tipo_impresion'=>'detallado',
            'pagos'=>[['metodo_pago'=>'tarjeta','monto'=>'120.00']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('anulada', $venta->fresh()->estado);
        $this->assertSame(100.0, (float) $cierre->fresh()->efectivo_esperado);
        $this->assertSame(1, CajaMovimiento::where('ajuste_venta',true)->count());
        $this->assertSame(['tarjeta'=>12000,'efectivo'=>-10000], app(CajaService::class)->arqueo($current)['ventas']);
        $this->assertSame('RSV-000099-C2', Venta::where('version',2)->sole()->numero);
        $this->assertSame(100.0, (float) app(DashboardSummaryService::class)->summarize(now()->subDay(),false)['paid']);
        $this->assertSame(20.0, (float) app(DashboardSummaryService::class)->summarize(now(),false)['paid']);
    }

    public function test_operator_can_view_but_cannot_edit(): void
    {
        $operator = User::factory()->create(['role'=>'operador']);
        $caja = CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$operator->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $venta = $this->venta($caja);
        $this->actingAs($operator)->get(route('admin.caja.ventas.index'))->assertOk();
        $this->get(route('admin.caja.ventas.show',$venta))->assertOk();
        $this->get(route('admin.caja.ventas.edit',$venta))->assertForbidden();
    }

    public function test_sale_correction_uses_the_same_checkout_with_original_payment_and_data(): void
    {
        $admin = User::factory()->create(['role'=>'super_admin']);
        $caja = CajaApertura::create(['nombre'=>'Caja 01','monto_inicial'=>0,'operador_id'=>$admin->id,'inicio_at'=>now(),'abierta_unica'=>1]);
        $venta = $this->venta($caja);
        $this->actingAs($admin)->get(route('admin.caja.ventas.edit',[$venta,'tipo'=>'venta']))
            ->assertOk()->assertSee('id="checkout"',false)->assertSee('data-kind="correccion"',false)
            ->assertSee('data-initial-payments=',false)->assertSee('RSV-000099')
            ->assertSee('Motivo obligatorio de la corrección')
            ->assertSee('id="preview-payment">Guardar y salir</button>',false);
        $this->get(route('admin.caja.ventas.edit',[$venta,'tipo'=>'pagos']))
            ->assertOk()->assertSee('id="checkout"',false)->assertSee('data-payment-only="1"',false);
    }
}
