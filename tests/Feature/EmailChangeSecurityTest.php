<?php
namespace Tests\Feature;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class EmailChangeSecurityTest extends TestCase {
    use RefreshDatabase;
    private function requestChange(User $user, string $email = 'new@example.test'): string {
        Notification::fake();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $email, 'current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $code = '';
        Notification::assertSentOnDemand(ConfirmEmailChange::class, function ($notification, $channels, $recipient) use (&$code, $email) {
            $code = $notification->code;
            return $recipient->routes['mail'] === $email;
        });
        return $code;
    }
    public function test_change_needs_code_and_code_is_single_use(): void {
        $user = User::factory()->create(); $old = $user->email;
        $code = $this->requestChange($user);
        $this->assertSame($old, $user->fresh()->email);
        $this->assertNotSame($code, $user->fresh()->email_change_hash);
        $this->post('/profile/email/confirm', ['code' => $code])->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->post('/profile/email/confirm', ['code' => $code])->assertSessionHasErrors('code');
    }
    public function test_expired_code_cannot_change_email(): void {
        $user = User::factory()->create(); $old = $user->email;
        $code = $this->requestChange($user);
        $this->travel(11)->minutes();
        $this->post('/profile/email/confirm', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame($old, $user->fresh()->email);
    }
    public function test_attempt_budget_persists_after_rate_limit_window(): void {
        $user = User::factory()->create(); $old = $user->email;
        $code = $this->requestChange($user);
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i=0; $i<5; $i++) { $this->travel(61)->seconds(); $this->post('/profile/email/confirm', ['code' => $wrong])->assertSessionHasErrors('code'); }
        $this->travel(61)->seconds();
        $this->post('/profile/email/confirm', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame($old, $user->fresh()->email);
    }
    public function test_code_cannot_be_used_by_another_account(): void {
        $user = User::factory()->create(); $code = $this->requestChange($user);
        $this->actingAs(User::factory()->create())->post('/profile/email/confirm', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNotSame('new@example.test', $user->fresh()->email);
    }
    public function test_cancel_invalidates_code(): void {
        $user = User::factory()->create(); $code = $this->requestChange($user);
        $this->delete('/profile/email')->assertRedirect();
        $this->post('/profile/email/confirm', ['code' => $code])->assertSessionHasErrors('code');
    }
    public function test_audit_contains_only_field_names_and_is_owner_only(): void {
        $owner = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($owner);
        $owner->update(['password' => 'very-private-test-password']);
        $event = DB::table('security_events')->orderByDesc('id')->first();
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertStringContainsString('password', $event->changed_fields);
        $this->assertStringNotContainsString('very-private', json_encode($event));
        $this->get('/admin/seguridad')->assertOk();
        $this->flushSession();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/seguridad')->assertForbidden();
    }
    public function test_new_request_invalidates_old_code_and_password_reset_clears_request(): void {
        $user = User::factory()->create();
        $this->requestChange($user);
        $user->forceFill(['email_change_hash' => hash_hmac('sha256', '000000', config('app.key'))])->save();
        $newCode = $this->requestChange($user, 'second@example.test');
        $this->post('/profile/email/confirm', ['code'=>'000000'])->assertSessionHasErrors('code');
        $this->assertSame('second@example.test', $user->fresh()->pending_email);
        $user->revokeAccess();
        $this->post('/profile/email/confirm', ['code'=>$newCode])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->pending_email);
    }
    public function test_audit_failure_rolls_back_profile_change(): void {
        $user = User::factory()->create(); $old = $user->email;
        $code = $this->requestChange($user);
        \Illuminate\Support\Facades\Schema::drop('security_events');
        $this->post('/profile/email/confirm', ['code'=>$code])->assertStatus(500);
        $this->assertSame($old, $user->fresh()->email);
    }
}
