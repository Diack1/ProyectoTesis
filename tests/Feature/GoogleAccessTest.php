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

    public function test_public_site_cannot_start_google_with_local_callback(): void
    {
        config(['services.google.client_id'=>'test', 'services.google.client_secret'=>'test',
            'services.google.redirect'=>'http://127.0.0.1:8000/auth/google/callback']);
        Socialite::shouldReceive('driver')->never();
        $this->get('https://parkeo.example/auth/google')->assertRedirect()
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_matching_callback_can_start_google(): void
    {
        config(['services.google.client_id'=>'test', 'services.google.client_secret'=>'test',
            'services.google.redirect'=>'http://localhost/auth/google/callback']);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
        Socialite::shouldReceive('scopes')->with(['openid','email','profile'])->once()->andReturnSelf();
        Socialite::shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com'));
        $this->get('/auth/google')->assertRedirect('https://accounts.google.com');
    }

    public function test_owner_is_directed_to_password_login_without_linking(): void
    {
        $owner = User::factory()->create(['email'=>'owner@gmail.com', 'role'=>'super_admin']);
        $this->google($owner->email);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))
            ->assertSessionHasErrors('google')->assertSessionMissing('google_link');
        $this->assertGuest();
        $this->assertNull($owner->fresh()->google_id);
        $this->assertSame('super_admin', $owner->fresh()->role);
    }

    public function test_linked_institutional_customer_keeps_customer_role_with_owner_present(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $customer = User::factory()->create(['email'=>'student@university.example','role'=>'user']);
        $customer->forceFill(['google_id'=>'google-123'])->save();
        $this->google($customer->email);
        $this->get('/auth/google/callback')->assertRedirect(route('reservas.index'));
        $this->assertAuthenticatedAs($customer);
        $this->assertSame('user', $customer->fresh()->role);
        $this->assertSame('super_admin', $owner->fresh()->role);
        $this->get('/admin')->assertForbidden();
    }

    public function test_repeated_link_attempts_show_readable_wait_without_authentication(): void
    {
        $user = User::factory()->create(['email'=>'rate-limit@gmail.com']);
        $session = ['google_link'=>['sub'=>'rate-limit','email'=>$user->email,'expires'=>now()->addMinutes(5)->timestamp]];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession($session)->post('/auth/google/link', ['password'=>'wrong'])->assertSessionHasErrors('password');
        }
        $this->withSession($session)->post('/auth/google/link', ['password'=>'password'])
            ->assertStatus(429)->assertSee('Espera un momento')->assertHeader('Retry-After');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }
}
