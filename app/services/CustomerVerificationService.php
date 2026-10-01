<?php
namespace App\Services;
use App\Models\User;
use App\Notifications\CustomerVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class CustomerVerificationService {
    private function hash(User $user, string $code): string {
        return hash_hmac('sha256', $user->id.'|'.$user->email.'|'.$code, config('app.key'));
    }
    public function send(User $user): void {
        abort_unless($user->tieneRol('user', 'admin', 'operador') && $user->activo,403);
        if ($user->hasVerifiedEmail()) { return; }
        if (!app(StaffAccessService::class)->mailReady()) {
            throw ValidationException::withMessages(['code'=>'El envío de correo no está disponible. Tu cuenta está creada; podrás solicitar el código cuando se restablezca.']);
        }
        $key='customer-verification:'.$user->id;
        if (RateLimiter::tooManyAttempts($key,1) || RateLimiter::tooManyAttempts($key.':hour',5)) {
            throw ValidationException::withMessages(['code'=>'Espera antes de reenviar. Puedes solicitar un código por minuto y hasta cinco por hora.']);
        }
        RateLimiter::hit($key,60); RateLimiter::hit($key.':hour',3600);
        DB::transaction(function() use($user) {
            $user=User::lockForUpdate()->findOrFail($user->id);
            if ($user->hasVerifiedEmail()) { return; }
            abort_unless($user->tieneRol('user', 'admin', 'operador') && $user->activo,403);
            $code=(string)random_int(100000,999999);
            $user->forceFill(['verification_code_hash'=>$this->hash($user,$code),
                'verification_code_expires_at'=>now()->addMinutes(10),'verification_code_attempts'=>0])->save();
            $user->notify(new CustomerVerificationCode($code));
        });
    }
    public function verify(User $user, string $code): bool {
        return DB::transaction(function() use($user,$code) {
            $user=User::lockForUpdate()->findOrFail($user->id);
            if (!$user->tieneRol('user', 'admin', 'operador') || !$user->activo || $user->hasVerifiedEmail() || !$user->verification_code_hash
                || !$user->verification_code_expires_at?->isFuture() || $user->verification_code_attempts>=5) { return false; }
            $user->increment('verification_code_attempts');
            if (!hash_equals($user->verification_code_hash,$this->hash($user,$code))) { return false; }
            $user->forceFill(['email_verified_at'=>now(),'verification_code_hash'=>null,
                'verification_code_expires_at'=>null,'verification_code_attempts'=>0])->save();
            event(new \Illuminate\Auth\Events\Verified($user));
            return true;
        });
    }
}
