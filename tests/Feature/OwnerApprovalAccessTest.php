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

    public function test_rejected_mail_credentials_do_not_grant_access_or_expose_details(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        \Illuminate\Support\Facades\Log::spy();
        Notification::shouldReceive('send')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('535 credentials rejected private-test-value'));
        $this->actingAs($owner)->post('/staff-access/request')->assertSessionHasErrors('access');
        $this->assertStringNotContainsString('private-test-value', session('errors')->first('access'));
        $this->assertStringContainsString('credenciales', session('errors')->first('access'));
        $this->assertDatabaseCount('staff_access_requests', 0);
        $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts('staff-code-send:'.$owner->id.':hour'));
        $this->get('/admin')->assertRedirect(route('staff-access.show'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with('access_mail_failed', ['reason'=>'authentication_rejected']);
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

    public function test_staff_activates_own_email_once_then_logs_in_without_owner_code(): void
    {
        $staff = User::factory()->unverified()->create(['role'=>'admin']);
        $this->post('/login',['email'=>$staff->email,'password'=>'password'])->assertRedirect(route('verification.notice'));
        $this->get('/admin')->assertRedirect(route('verification.notice'));
        $mail = Notification::sent($staff, \App\Notifications\CustomerVerificationCode::class)->last();
        $this->assertNotNull($mail);
        $this->post('/verify-email',['code'=>$mail->code])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk();
        $this->post('/logout');
        Notification::fake();
        $this->post('/login',['email'=>$staff->email,'password'=>'password'])->assertRedirect(route('admin.dashboard'));
        Notification::assertNothingSent();
        $this->get('/admin')->assertOk();
        $this->post('/staff-access/request')->assertForbidden();
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
        $this->actingAs($owner)->post('/staff-access/request');
        $code = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, StaffAccessMail::class)->last()->code;
        $owner->update(['email'=>'changed@example.test']);
        $this->post('/staff-access/verify',['code'=>$code])->assertSessionHasErrors('access');
    }

    public function test_mail_is_limited_and_customer_cannot_request_staff_access(): void
    {
        $owner = User::factory()->create(['role'=>'super_admin']);
        $this->actingAs(User::factory()->create(['role'=>'user']))->get('/staff-access')->assertForbidden();
        $this->post('/staff-access/request')->assertForbidden();
        $this->flushSession();
        $this->actingAs($owner)->post('/staff-access/request')->assertRedirect();
        $this->post('/staff-access/request')->assertRedirect(route('staff-access.show'))->assertSessionHasErrors('access');
        $this->get('/staff-access')->assertOk()->assertSee('Podrás solicitar otro código');
        $this->assertDatabaseCount('staff_access_requests', 1);
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
        $owner = User::factory()->create(['role'=>'super_admin']);
        $this->actingAs($owner)->post('/staff-access/request');
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
