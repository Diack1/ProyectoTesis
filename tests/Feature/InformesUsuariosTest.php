<?php

namespace Tests\Feature;

use App\Models\{CajaApertura, User, Venta};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformesUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function venta(CajaApertura $caja, string $numero): Venta
    {
        return Venta::create(['caja_apertura_id' => $caja->id, 'origen_tipo' => 'reserva', 'origen_id' => $caja->id,
            'version' => 1, 'numero' => $numero, 'fecha' => now(), 'cliente' => 'Cliente',
            'tipo_comprobante' => 'boleta', 'tipo_impresion' => 'detallado', 'concepto' => 'ABC123',
            'importe_base' => 100, 'descuento' => 0, 'total' => 100, 'datos' => ['placa' => 'ABC123']]);
    }

    public function test_junior_sees_only_sales_of_own_open_cash_and_admin_sees_all_reports(): void
    {
        $junior = User::factory()->create(['role' => 'junior']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $closed = CajaApertura::create(['nombre' => 'Caja 01', 'monto_inicial' => 0, 'operador_id' => $junior->id,
            'inicio_at' => now()->subDay(), 'abierta_unica' => null]);
        $old = $this->venta($closed, 'OLD-001');
        $open = CajaApertura::create(['nombre' => 'Caja 01', 'monto_inicial' => 0, 'operador_id' => $junior->id,
            'inicio_at' => now(), 'abierta_unica' => 1]);
        $current = $this->venta($open, 'NOW-001');

        $this->actingAs($junior)->get(route('admin.caja.ventas.index'))->assertOk()->assertSee('NOW-001')->assertDontSee('OLD-001');
        $this->get(route('admin.caja.ventas.show', $old))->assertForbidden();
        $this->get(route('admin.caja.ventas.show', $current))->assertOk();
        $this->get(route('admin.informes.ventas'))->assertForbidden();
        $this->get(route('superadmin.dashboard'))->assertForbidden();
        $this->get(route('admin.tarifas.index'))->assertForbidden();
        $open->update(['abierta_unica' => null]);
        $this->get(route('admin.caja.ventas.index'))->assertOk()->assertDontSee('NOW-001')->assertDontSee('OLD-001');
        $this->actingAs($admin)->get(route('admin.informes.ventas'))->assertOk()->assertSee('NOW-001')->assertSee('OLD-001');
        $this->get(route('admin.informes.tickets'))->assertOk();
        $this->get(route('admin.informes.descuentos'))->assertOk();
        $this->get(route('admin.informes.anuladas'))->assertOk();
        $this->get(route('admin.informes.ventas.exportar'))->assertOk();
    }

    public function test_last_admin_stays_active(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->patch(route('superadmin.admins.toggleActivo', $admin))->assertSessionHasErrors('usuario');
        $other = User::factory()->create(['role' => 'super_admin']);
        $this->patch(route('superadmin.admins.toggleActivo', $other))->assertRedirect();
        $this->assertFalse($other->fresh()->activo);
        $this->assertTrue($admin->fresh()->activo);
    }
}
