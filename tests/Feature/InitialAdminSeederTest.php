<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_initial_admin_can_log_in_and_reseeding_keeps_its_password(): void
    {
        config()->set('app.initial_admin_email', 'admin@example.test');
        config()->set('app.initial_admin_password', 'Admin-inicial-123!');

        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->activo);
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('Admin-inicial-123!', $admin->password));

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'Admin-inicial-123!'])
            ->assertRedirect(route('admin.dashboard'));

        $originalHash = $admin->password;
        config()->set('app.initial_admin_password', 'Otra-clave-456!');
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertSame(1, User::where('email', $admin->email)->count());
    }
}
