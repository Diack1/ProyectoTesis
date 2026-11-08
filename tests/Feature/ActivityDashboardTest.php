<?php

namespace Tests\Feature;

use App\Models\{CajaApertura, CajaMovimiento, Espacio, Estadia, Pago, User, VehiculoTipo, Venta};
use App\Services\ActivityDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function sale(CajaApertura $box, string $time, int $id, string $method, float $amount, float $discount = 0): void
    {
        $venta = Venta::create(['caja_apertura_id' => $box->id, 'origen_tipo' => 'reserva', 'origen_id' => $id,
            'version' => 1, 'numero' => 'TEST-'.$id, 'fecha' => $time, 'cliente' => 'Cliente',
            'tipo_comprobante' => 'boleta', 'tipo_impresion' => 'detallado', 'concepto' => 'ABC123',
            'importe_base' => $amount + $discount, 'descuento' => $discount, 'total' => $amount]);
        Pago::create(['venta_id' => $venta->id, 'caja_apertura_id' => $box->id, 'codigo_pago' => 'P-'.$id,
            'metodo_pago' => $method, 'monto' => $amount, 'estado' => 'aprobado', 'pagado_at' => $time]);
    }

    public function test_night_shift_crosses_midnight_and_excludes_six_am(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $box = CajaApertura::create(['nombre' => 'Caja 01', 'monto_inicial' => 0,
            'operador_id' => $admin->id, 'inicio_at' => now(), 'abierta_unica' => 1]);
        $this->sale($box, '2026-10-08 19:00:00', 1, 'efectivo', 20, 5);
        $this->sale($box, '2026-10-09 05:59:00', 2, 'yape', 30);
        $this->sale($box, '2026-10-09 06:00:00', 3, 'tarjeta', 40);
        $this->sale($box, '2026-10-09 19:00:00', 4, 'plin', 10);
        $this->sale($box, '2026-10-10 05:30:00', 5, 'manual', 15);
        $expense = CajaMovimiento::create(['caja_apertura_id' => $box->id, 'operador_id' => $admin->id,
            'tipo' => 'egreso', 'metodo_pago' => 'efectivo', 'monto' => 3,
            'motivo' => 'Compra de suministros']);
        $expense->forceFill(['created_at' => '2026-10-09 02:00:00'])->save();
        $space = Espacio::create(['codigo' => 'A1', 'estado_actual' => 'libre', 'activo' => true]);
        $type = VehiculoTipo::create(['nombre' => 'Automóvil', 'codigo' => 'auto', 'activo' => true]);
        Estadia::create(['codigo_ticket' => 'T-1', 'placa' => 'ABC123', 'espacio_id' => $space->id,
            'vehiculo_tipo_id' => $type->id, 'operador_ingreso_id' => $admin->id,
            'hora_ingreso' => '2026-10-08 18:00:00', 'hora_salida' => '2026-10-09 05:30:00',
            'fuente_inicio' => 'manual', 'tarifa_snapshot' => []]);

        $summary = app(ActivityDashboardService::class)->summarize(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-08'), 'noche', $admin);
        $this->assertSame('19:00', $summary['rows'][0]['label']);
        $this->assertSame('05:00', $summary['rows'][10]['label']);
        $this->assertSame(2, $summary['sales_count']);
        $this->assertSame(50.0, $summary['sales_total']);
        $this->assertSame(5.0, $summary['discounts']);
        $this->assertSame(1, $summary['exits']);
        $this->assertSame(3.0, $summary['expenses']);
        $this->assertSame(30.0, $summary['rows'][10]['ingreso']);
        $this->assertSame(0.0, $summary['methods']['tarjeta']);
        $morning = app(ActivityDashboardService::class)->summarize(Carbon::parse('2026-10-09'), Carbon::parse('2026-10-09'), 'manana', $admin);
        $this->assertSame(1, $morning['sales_count']);
        $this->assertSame(40.0, $morning['methods']['tarjeta']);
        $range = app(ActivityDashboardService::class)->summarize(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-09'), 'noche', $admin);
        $this->assertSame(4, $range['sales_count']);
        $this->assertSame(75.0, $range['sales_total']);
        $this->assertSame(10.0, $range['methods']['plin']);
        $this->assertSame(15.0, $range['methods']['manual']);
        $this->actingAs($admin)->get(route('admin.resumen.index', ['modo' => 'rango',
            'desde' => '2026-10-08', 'hasta' => '2026-10-09', 'turno' => 'noche']))
            ->assertOk()->assertViewHas('summary', fn ($data) => $data['sales_count'] === 4);
    }

    public function test_chart_choice_keeps_base_cards_and_junior_cannot_query_past_dates(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $day = '2026-10-08';
        $sales = $this->actingAs($admin)->get(route('admin.resumen.index', ['fecha' => $day, 'grafico' => 'ventas']))->assertOk();
        $income = $this->get(route('admin.resumen.index', ['fecha' => $day, 'grafico' => 'ingreso']))->assertOk();
        $this->assertSame($sales->viewData('summary')['methods'], $income->viewData('summary')['methods']);
        $this->assertSame($sales->viewData('summary')['sales_total'], $income->viewData('summary')['sales_total']);
        $income->assertSee('Ingreso por hora');
        $income->assertSee('stroke-linecap="round"', false)->assertDontSee('<polyline', false);
        $junior = User::factory()->create(['role' => 'junior']);
        $this->actingAs($junior)->get(route('admin.resumen.index', ['fecha' => $day]))->assertForbidden();
        $this->get(route('admin.resumen.index', ['modo' => 'rango', 'desde' => $day, 'hasta' => '2026-10-10']))->assertForbidden();
    }
}
