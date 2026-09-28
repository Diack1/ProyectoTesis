<?php
namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\ReporteController;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_never_creates_or_resets_owner(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $owner = User::factory()->create(['role' => 'super_admin', 'activo' => false]);
        $hash = $owner->password;
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertFalse($owner->fresh()->activo);
    }

    public function test_inactive_account_cannot_use_profile(): void
    {
        $user = User::factory()->create(['activo' => false]);
        $this->actingAs($user)->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_email_change_requires_password(): void
    {
        $user = User::factory()->create();
        $email = $user->email;
        $this->actingAs($user)->patch('/profile', ['name' => 'Test', 'email' => 'other@example.test'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_telemetry_is_not_public_or_customer_accessible(): void
    {
        $this->getJson('/api/espacios/estado')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/espacios/estado')->assertForbidden();
    }

    public function test_authenticated_pages_are_not_cacheable(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_csv_formula_is_neutralized(): void
    {
        foreach (['=1+1', '+1+1', '@SUM(A1)', '-1+1', "\t=1+1"] as $value) {
            $this->assertSame("'".$value, ReporteController::csvText($value));
        }
        $this->assertSame('E01', ReporteController::csvText('E01'));
    }

    public function test_password_change_revokes_api_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');
        $this->actingAs($user)->put('/password', ['current_password' => 'password',
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertSame(0, $user->tokens()->count());
    }
    public function test_closing_account_preserves_paid_reservation(): void
    {
        $user = User::factory()->create();
        $space = \App\Models\Espacio::create(['codigo' => 'E99', 'estado_actual' => 'libre', 'activo' => true]);
        $reservation = \App\Models\Reserva::create(['user_id' => $user->id, 'espacio_id' => $space->id,
            'codigo_reserva' => 'TEST-RES', 'fecha_reserva' => today()->toDateString(),
            'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'estado' => 'confirmada', 'monto_total' => 10]);
        $payment = \App\Models\Pago::create(['user_id' => $user->id, 'reserva_id' => $reservation->id,
            'codigo_pago' => 'TEST-PAY', 'metodo_pago' => 'yape', 'estado' => 'aprobado', 'monto' => 10]);
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertDatabaseHas('reservas', ['id' => $reservation->id]);
        $this->assertDatabaseHas('pagos', ['id' => $payment->id, 'monto' => 10]);
    }

    public function test_stale_refund_decision_cannot_overwrite_processed_refund(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $space = \App\Models\Espacio::create(['codigo' => 'E99', 'estado_actual' => 'libre', 'activo' => true]);
        $reservation = \App\Models\Reserva::create(['user_id' => $user->id, 'espacio_id' => $space->id,
            'codigo_reserva' => 'TEST-RES', 'fecha_reserva' => today()->toDateString(),
            'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'estado' => 'reembolso_solicitado', 'monto_total' => 10]);
        $payment = \App\Models\Pago::create(['user_id' => $user->id, 'reserva_id' => $reservation->id,
            'codigo_pago' => 'TEST-PAY', 'metodo_pago' => 'yape', 'estado' => 'aprobado', 'monto' => 10]);
        $refund = \App\Models\Reembolso::create(['user_id' => $user->id, 'reserva_id' => $reservation->id,
            'pago_id' => $payment->id, 'monto' => 10, 'estado' => 'solicitado', 'motivo' => 'Prueba', 'solicitado_at' => now()]);
        $stale = $refund->fresh();
        $this->actingAs($user);
        $controller = app(\App\Http\Controllers\Admin\ReservaAdminController::class);
        $controller->aprobarReembolso($refund);
        try {
            $controller->rechazarReembolso($stale);
            $this->fail('La decisión antigua debe rechazarse.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('aprobado', $refund->fresh()->estado);
        $this->assertSame('reembolsado', $payment->fresh()->estado);
    }
}
