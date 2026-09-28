<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceptionNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_and_legacy_operator_have_four_links_and_cannot_open_configuration(): void
    {
        foreach (['admin','operador'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            $page = $this->get(route('admin.dashboard'))->assertOk();
            $page->assertSee('Entradas y salidas')->assertSee('Registrar entrada')->assertSee('Ver reservas de hoy')
                ->assertDontSee('<summary>Configuración</summary>', false);
            foreach (['admin.espacios.index','admin.tarifas.index','admin.sensores.index','admin.pagos.configuracion','superadmin.dashboard','admin.reportes.index','admin.reportes.exportarCsv','sensores.estado'] as $route) {
                $this->get(route($route))->assertForbidden();
            }
            $this->post(route('superadmin.admins.store'), ['name'=>'No autorizado'])->assertForbidden();
            $this->put(route('admin.pagos.configuracion.update'), [])->assertForbidden();
            foreach (['admin.estadias.index','admin.estadias.create','admin.reservas.index','admin.pagos.index','admin.clientes-vehiculos.index','admin.placas.index'] as $route) {
                $this->get(route($route))->assertOk();
            }
        }
    }

    public function test_owner_keeps_configuration_and_customer_cannot_open_staff_pages(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'super_admin']));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('<summary>Configuración</summary>',false);
        foreach (['admin.espacios.index','admin.tarifas.index','admin.sensores.index','admin.pagos.configuracion','superadmin.dashboard'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->create(['role'=>'user']))->get(route('admin.dashboard'))->assertForbidden();
    }
}
