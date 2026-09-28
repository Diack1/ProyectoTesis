<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StaffAccessRequest;
use App\Notifications\StaffAccessMail;
use App\Services\StaffAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OwnerApprovalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.require_staff_mfa'=>true, 'security.alert_email'=>null]);
        Notification::fake();
    }

    private function ownerLogin(User $owner): void
    {
        $this->flushSession();
        $this->actingAs($owner)->post('/staff-access/request')->assertRedirect();
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        $this->post('/staff-access/verify', ['code'=>$code])->assertRedirect(route('admin.dashboard'));
    }

    public function test_owner_email_code_is_required_and_totp_is_removed(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $this->actingAs($owner)->get('/admin')->assertRedirect(route('staff-access.show'));
        $this->get('/staff-access')->assertOk()->assertSee('Revisa tu correo');
        $this->get('/two-factor')->assertNotFound();
        $this->ownerLogin($owner);
        $this->get('/admin')->assertOk();
        $entry = StaffAccessRequest::first();
        $this->assertSame('consumed', $entry->state);
        $this->assertNull($entry->code_hash);
        $this->post('/logout')->assertRedirect('/');
        $this->actingAs($owner)->get('/admin')->assertRedirect(route('staff-access.show'));
    }

    public function test_staff_code_goes_only_to_owner_and_is_bound_to_session(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $staff = User::factory()->create(['role'=>'admin']);
        $this->post('/login',['email'=>$staff->email,'password'=>'password'])->assertRedirect(route('staff-access.show'));
        $entry = StaffAccessRequest::first();
        $nonce = session('staff_access_nonce');
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        Notification::assertSentOnDemand(StaffAccessMail::class, fn($mail,$channels,$recipient)=>$mail->code === $code && !$mail->isOwner && $recipient->routes['mail'] === $owner->email);
        $this->get('/admin')->assertRedirect(route('staff-access.show'));
        $this->withSession(['staff_access_nonce'=>'different'])->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
        $this->withSession(['staff_access_nonce'=>$nonce])->post('/staff-access/verify',['code'=>$code])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('consumed',$entry->fresh()->state);
        $this->assertNull($entry->fresh()->code_hash);
        $this->get('/admin')->assertOk();
        $this->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
    }

    public function test_expired_code_and_five_wrong_attempts_cannot_grant_access(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $this->actingAs($owner)->post('/staff-access/request');
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        for ($i=0;$i<5;$i++) {
            $this->post('/staff-access/verify',['code'=>'000000'])->assertSessionHasErrors('access');
        }
        $this->travel(61)->seconds();
        $this->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
        $this->assertSame(5, StaffAccessRequest::first()->attempts);
        $this->post('/staff-access/request')->assertRedirect();
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        $this->travel(6)->minutes();
        $this->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
        $this->get('/admin')->assertRedirect(route('staff-access.show'));
    }

    public function test_changed_owner_invalidates_pending_code(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $staff = User::factory()->create(['role'=>'operador']);
        $this->actingAs($staff)->post('/staff-access/request');
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        $owner->update(['email'=>'changed@example.test']);
        $this->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
    }

    public function test_mail_is_limited_and_customer_cannot_request_staff_access(): void
    {
        User::factory()->create(['role'=>'super_admin']);
        $this->actingAs(User::factory()->create(['role'=>'user']))->get('/staff-access')->assertForbidden();
        $this->post('/staff-access/request')->assertForbidden();
        $this->flushSession();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post('/staff-access/request')->assertRedirect();
        $this->post('/staff-access/request')->assertStatus(429);
    }

    public function test_owner_email_transfer_disables_customer_and_keeps_owner_password(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $password = $owner->password;
        $customer = User::factory()->create(['email'=>'owner@example.test']);
        $this->artisan('security:assign-owner-email',['email'=>$customer->email])->assertSuccessful();
        $this->assertSame('owner@example.test', $customer->fresh()->email);
        $this->artisan('security:assign-owner-email',['email'=>$customer->email,'--apply'=>true])->assertSuccessful();
        $this->assertSame('owner@example.test',$owner->fresh()->email);
        $this->assertSame($password,$owner->fresh()->password);
        $this->assertFalse($customer->fresh()->activo);
        $this->assertNotSame('owner@example.test',$customer->fresh()->email);
        $this->assertDatabaseCount('users',2);
        $this->artisan('security:assign-owner-email',['email'=>'owner@example.test','--apply'=>true])->assertSuccessful();
    }

    public function test_old_approval_cannot_bypass_code(): void
    {
        User::factory()->create(['role'=>'super_admin']);
        $staff = User::factory()->create(['role'=>'admin']);
        $this->actingAs($staff)->post('/staff-access/request');
        StaffAccessRequest::first()->update(['state'=>'approved','kind'=>'staff_approval']);
        $this->post('/staff-access/verify',['code'=>'123456'])->assertSessionHasErrors('access');
        $this->get('/admin')->assertRedirect(route('staff-access.show'));
    }

    public function test_missing_real_mail_configuration_cannot_grant_access_or_send_to_logs(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $this->app->detectEnvironment(fn()=>'local');
        config(['security.access_mail_ready'=>false,'mail.default'=>'log']);
        $this->actingAs($owner)->get('/staff-access')->assertOk()->assertSee('Falta configurar')->assertSee('name="code"', false)->assertSee('Aún no se ha enviado');
        $request = \Illuminate\Http\Request::create('/staff-access/request', 'POST');
        $request->setUserResolver(fn()=>$owner);
        $request->setLaravelSession(app('session.store'));
        try {
            app(StaffAccessService::class)->start($request);
            $this->fail('Unconfigured email must block access.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('access', $exception->errors());
        }
        $this->assertDatabaseCount('staff_access_requests',0);
        Notification::assertNothingSent();
        $this->get('/admin')->assertRedirect(route('staff-access.show'));
    }
}
