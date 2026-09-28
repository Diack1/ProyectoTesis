<?php
namespace Tests\Feature\Auth;
use App\Models\User;
use App\Notifications\CustomerVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
class EmailVerificationTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Notification::fake(); }
    private function sendCode(User $user): string {
        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect();
        return Notification::sent($user,CustomerVerificationCode::class)->last()->code;
    }
    public function test_registration_sends_only_to_customer_and_requires_verification(): void {
        User::factory()->create(['role'=>'super_admin']);
        $this->post('/register',['name'=>'Cliente','email'=>'cliente@example.test','password'=>'password','password_confirmation'=>'password'])->assertRedirect(route('verification.notice'));
        $user=User::where('email','cliente@example.test')->firstOrFail();
        Notification::assertSentTo($user,CustomerVerificationCode::class);
        Notification::assertCount(1);
        $this->assertNull($user->email_verified_at);
        $this->get('/verify-email')->assertOk()->assertSee('Código de seis dígitos');
        $this->get('/reservas')->assertRedirect(route('verification.notice'));
        $this->getJson('/reservas')->assertForbidden();
    }
    public function test_code_is_hashed_one_time_and_unlocks_reservations(): void {
        $user=User::factory()->unverified()->create();
        $code=$this->sendCode($user);
        $this->assertNotSame($code,$user->fresh()->verification_code_hash);
        $this->assertStringNotContainsString('verification_code_hash',$user->fresh()->toJson());
        $this->post('/verify-email',['code'=>$code])->assertRedirect(route('reservas.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->verification_code_hash);
        $this->actingAs($user->fresh())->get('/reservas')->assertOk();
        $this->post('/verify-email',['code'=>$code])->assertSessionHasErrors('code');
    }
    public function test_expiry_resend_and_attempt_limit(): void {
        $user=User::factory()->unverified()->create();
        $code=$this->sendCode($user);
        $this->post('/email/verification-notification')->assertSessionHasErrors('code');
        for($i=0;$i<5;$i++) { $this->post('/verify-email',['code'=>'000000'])->assertSessionHasErrors('code'); }
        $this->travel(61)->seconds();
        $this->post('/verify-email',['code'=>$code])->assertSessionHasErrors('code');
        $code=$this->sendCode($user);
        $this->travel(11)->minutes();
        $this->post('/verify-email',['code'=>$code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
    public function test_other_user_cannot_use_code_and_email_change_invalidates_it(): void {
        $user=User::factory()->unverified()->create();
        $code=$this->sendCode($user);
        $other=User::factory()->unverified()->create();
        $this->flushSession();
        $this->actingAs($other)->post('/verify-email',['code'=>$code])->assertSessionHasErrors('code');
        $this->flushSession();
        $user->update(['email'=>'different@example.test']);
        $this->actingAs($user)->post('/verify-email',['code'=>$code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
    public function test_legacy_link_route_is_removed(): void {
        $user=User::factory()->unverified()->create();
        $this->actingAs($user)->get('/verify-email/'.$user->id.'/'.sha1($user->email))->assertNotFound();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
