<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_junior_uses_assigned_password_and_admin_can_still_log_in(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'password' => Hash::make('Admin-strong-123!')]);
        $this->actingAs($admin)->post(route('superadmin.admins.store'), [
            'name' => 'Junior de prueba', 'email' => 'junior@example.test',
            'password' => 'Junior-start-123!', 'password_confirmation' => 'Junior-start-123!',
            'role' => 'junior', 'activo' => '1',
        ])->assertRedirect(route('superadmin.dashboard'))->assertSessionHasNoErrors();
        $junior = User::where('email', 'junior@example.test')->firstOrFail();
        $this->assertNotNull($junior->email_verified_at);
        $this->assertFalse($junior->must_change_password);

        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('login'), ['email' => $junior->email, 'password' => 'Junior-start-123!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'Admin-strong-123!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_existing_flagged_staff_is_not_forced_to_change_password(): void
    {
        $junior = User::factory()->create(['role' => 'junior', 'email_verified_at' => now(),
            'must_change_password' => true, 'password' => Hash::make('Junior-start-123!')]);
        $this->post(route('login'), ['email' => $junior->email, 'password' => 'Junior-start-123!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_reset_password_is_used_directly_by_new_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('superadmin.admins.store'), [
            'name' => 'Otro administrador', 'email' => 'otro-admin@example.test',
            'password' => 'Admin-start-123!', 'password_confirmation' => 'Admin-start-123!',
            'role' => 'super_admin', 'activo' => '1',
        ])->assertRedirect(route('superadmin.dashboard'));
        $other = User::where('email', 'otro-admin@example.test')->firstOrFail();
        $this->assertFalse($other->must_change_password);
        $this->put(route('superadmin.admins.password', $other), [
            'password' => 'Admin-reset-456!', 'password_confirmation' => 'Admin-reset-456!',
        ])->assertRedirect(route('superadmin.dashboard'))->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->must_change_password);
        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('login'), ['email' => $other->email, 'password' => 'Admin-reset-456!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
