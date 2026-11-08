<?php

namespace Tests\Feature;

use App\Models\{CajaApertura, CajaCierre, CajaMovimiento, Pago, User};
use App\Services\CobroPresencialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CajaJornadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_open_movements_live_arqueo_and_close_keep_cash_change_out_of_income(): void
    {
        $staff = User::factory()->create(['role' => 'operador']);
        $this->actingAs($staff)->get(route('admin.caja.index'))->assertOk()->assertSee('Caja cerrada');
        $this->post(route('admin.caja.abrir'), ['monto_inicial' => '20.00'])->assertSessionHasNoErrors();
        $this->post(route('admin.caja.abrir'), ['monto_inicial' => '30.00'])->assertSessionHasErrors('caja');
        $caja = CajaApertura::sole();
        $this->assertSame('20.00', $caja->monto_inicial);
        $this->assertSame($staff->id, $caja->operador_id);

        Pago::create(['codigo_pago' => 'CASH', 'caja_apertura_id' => $caja->id,
            'metodo_pago' => 'efectivo', 'monto' => 25, 'efectivo_recibido' => 50,
            'vuelto' => 25, 'estado' => 'aprobado', 'pagado_at' => now()]);
        Pago::create(['codigo_pago' => 'CARD', 'caja_apertura_id' => $caja->id,
            'metodo_pago' => 'tarjeta', 'monto' => 25,
            'estado' => 'aprobado', 'pagado_at' => now()]);
        $this->post(route('admin.caja.movimiento'), ['tipo' => 'ingreso', 'metodo_pago' => 'efectivo',
            'monto' => 10, 'motivo' => 'Fondo adicional'])->assertSessionHasNoErrors();
        $this->post(route('admin.caja.movimiento'), ['tipo' => 'egreso', 'metodo_pago' => 'efectivo',
            'monto' => 5, 'motivo' => 'Compra de insumos'])->assertSessionHasNoErrors();
        $this->post(route('admin.caja.movimiento'), ['tipo' => 'ingreso', 'metodo_pago' => 'yape',
            'monto' => 7, 'motivo' => 'Ajuste verificado'])->assertSessionHasNoErrors();
        $this->post(route('admin.caja.movimiento'), ['tipo' => 'egreso', 'metodo_pago' => 'efectivo',
            'monto' => 1])->assertSessionHasErrors('motivo');
        $this->assertSame(3, CajaMovimiento::count());
        $this->get(route('admin.caja.index'))->assertOk()->assertSee('S/ 50.00')->assertSee('S/ 82.00');

        $this->post(route('admin.caja.cerrar'), ['efectivo_contado' => 48,
            'observacion' => 'Faltan dos soles'])->assertSessionHasNoErrors();
        $cierre = CajaCierre::sole();
        $this->assertSame(50.0, (float) $cierre->efectivo_esperado);
        $this->assertSame(-2.0, (float) $cierre->diferencia);
        $this->assertSame(20.0, (float) $cierre->monto_inicial);
        $this->assertSame(25.0, (float) $cierre->totales_por_medio['efectivo']);
        $this->assertSame(25.0, (float) $cierre->totales_por_medio['tarjeta']);
        $this->assertSame(2, Pago::where('caja_cierre_id', $cierre->id)->count());
        $this->assertNull($caja->fresh()->abierta_unica);
        $this->post(route('admin.caja.movimiento'), ['tipo' => 'ingreso', 'metodo_pago' => 'efectivo',
            'monto' => 1, 'motivo' => 'Otro fondo'])->assertSessionHasErrors('caja');
        $this->post(route('admin.caja.abrir'), ['monto_inicial' => 48])->assertSessionHasNoErrors();
        $this->assertSame(2, CajaApertura::count());
    }

    public function test_presential_charge_requires_open_cash_register(): void
    {
        $staff = User::factory()->create(['role' => 'operador']);
        $this->expectException(ValidationException::class);
        app(CobroPresencialService::class)->registrar(['pago_verificado' => 1,
            'pagos' => [['metodo_pago' => 'efectivo', 'monto' => 10, 'efectivo_recibido' => 10]]],
            1000, $staff, []);
    }
}
