<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function google(string $email, string $id = 'google-123', bool $verified = true): void
    {
        config(['services.google.client_id'=>'test', 'services.google.client_secret'=>'test', 'services.google.redirect'=>'https://example.test/auth/google/callback']);
        $identity = (new GoogleUser)->map(['id'=>$id, 'email'=>$email, 'name'=>'Cliente Google']);
        $identity->user = ['email_verified'=>$verified];
        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($identity);
    }

    public function test_google_is_hidden_and_unavailable_without_configuration(): void
    {
        config(['services.google.client_id'=>null]);
        $this->get('/login')->assertDontSee('Continuar con Google');
        $this->get('/auth/google')->assertNotFound();
    }

    public function test_new_google_account_is_customer_and_preserves_intended_destination(): void
    {
        $this->google('customer@gmail.com');
        $target = url('/reservas/crear/1?vehiculo_tipo_id=2&duracion_minutos=120');
        $this->withSession(['url.intended'=>$target])->get('/auth/google/callback')->assertRedirect($target);
        $user = User::first();
        $this->assertSame('user', $user->role);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_account_requires_password_link_and_keeps_same_id(): void
    {
        $user = User::factory()->create(['email'=>'customer@gmail.com']);
        $this->google($user->email);
        $this->get('/auth/google/callback')->assertRedirect(route('google.link'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
        $this->post('/auth/google/link',['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->post('/auth/google/link',['password'=>'password'])->assertRedirect(route('reservas.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users',1);
    }

    public function test_google_cannot_link_staff_or_disabled_accounts(): void
    {
        foreach (['admin','super_admin'] as $role) {
            $user = User::factory()->create(['role'=>$role]);
            $this->withSession(['google_link'=>['sub'=>'id-'.$role, 'email'=>$user->email,'expires'=>now()->addMinutes(5)->timestamp]])
                ->post('/auth/google/link',['password'=>'password'])->assertSessionHasErrors('password');
            $this->assertGuest();
            $this->assertNull($user->fresh()->google_id);
        }
        $user = User::factory()->create(['email'=>'disabled@gmail.com','activo'=>false]);
        $user->forceFill(['google_id'=>'google-123'])->save();
        $this->google($user->email);
        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unverified_identity_or_expired_link_cannot_authenticate(): void
    {
        $this->google('test@gmail.com','google-123',false);
        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertDatabaseCount('users',0);
        $this->withSession(['google_link'=>['sub'=>'test','email'=>'test@gmail.com','expires'=>now()->subMinute()->timestamp]])
            ->post('/auth/google/link',['password'=>'password'])->assertForbidden();
        $this->assertGuest();
    }

    public function test_state_failure_is_rejected(): void
    {
        config(['services.google.client_id'=>'test','services.google.client_secret'=>'test','services.google.redirect'=>'https://example.test/callback']);
        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow(new \Laravel\Socialite\Two\InvalidStateException);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }
}
