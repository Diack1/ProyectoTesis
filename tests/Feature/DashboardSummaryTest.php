<?php

namespace Tests\Feature;

use App\Models\{Pago, User};
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_exclude_pending_and_period_end(): void
    {
        $date = \Carbon\Carbon::parse('2026-09-15 00:00:00');
        $user = User::factory()->create();
        foreach ([['aprobado',10,$date->copy()->addHours(9)], ['reembolsado',5,$date->copy()->addHours(10)],
            ['pendiente',20,$date->copy()->addHours(11)], ['aprobado',40,$date->copy()->addDay()]] as $i=>[$state,$amount,$time]) {
            Pago::create(['user_id'=>$user->id,'codigo_pago'=>'TEST-'.$i,'metodo_pago'=>'yape','monto'=>$amount,'estado'=>$state,'pagado_at'=>$time,'enviado_at'=>$time]);
        }
        $summary = app(DashboardSummaryService::class)->summarize($date,false);
        $this->assertEquals(15,$summary['paid']);
        $this->assertEquals(20,$summary['pending']);
        $this->assertEquals(10,$summary['rows'][9]['paid']);
        $this->assertCount(24,$summary['rows']);
        $monthly = app(DashboardSummaryService::class)->summarize($date,true);
        $this->assertEquals(55,$monthly['paid']);
        $this->assertCount(30,$monthly['rows']);
    }

    public function test_only_owner_can_request_monthly_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin?periodo=mes')->assertOk()->assertSee('Resumen del día')->assertDontSee('Resumen mensual');
        $this->actingAs(User::factory()->create(['role'=>'super_admin']))->get('/admin?periodo=mes')->assertOk()->assertSee('Resumen mensual');
        $this->actingAs(User::factory()->create())->get('/admin?periodo=mes')->assertForbidden();
    }

    public function test_staff_cannot_manage_accounts_or_see_owner_financial_chart(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->get('/admin')->assertOk()->assertSee('Entradas y salidas por hora')
            ->assertDontSee('Cobros y reembolsos')->assertDontSee(route('superadmin.dashboard'));
        $this->get(route('superadmin.admins.create'))->assertForbidden();
        $this->post(route('superadmin.admins.store'), [])->assertForbidden();
    }

    public function test_owner_can_create_staff_who_still_need_email_activation(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'super_admin']));
        $this->get('/admin')->assertOk()->assertSee('Cobros y reembolsos')->assertSee(route('superadmin.dashboard'));
        $this->get(route('superadmin.admins.create'))->assertOk()->assertSee('correo real del trabajador');
        $this->post(route('superadmin.admins.store'), [
            'name'=>'Recepción prueba','email'=>'staff@example.test','role'=>'admin','activo'=>'1',
            'password'=>'Test-password-123','password_confirmation'=>'Test-password-123',
        ])->assertRedirect(route('superadmin.dashboard'));
        $staff = User::where('email','staff@example.test')->firstOrFail();
        $this->assertSame('admin', $staff->role);
        $this->assertNull($staff->email_verified_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Test-password-123', $staff->password));
    }
}
