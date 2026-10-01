<?php

namespace App\Services;

use App\Models\User;
use App\Models\StaffAccessRequest;
use App\Notifications\StaffAccessMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffAccessService
{
    public function entryRoute(): string { return 'staff-access.show'; }

    public function required(User $user): bool
    {
        return $user->esSuperAdmin() && (bool)config('security.require_staff_mfa');
    }

    public function fingerprint(User $user): string
    {
        return hash('sha256', 'owner-approval|'.$user->id.'|'.$user->password.'|'.$user->email.'|'.$user->role.'|'.(int)$user->activo);
    }

    public function owner(): ?User
    {
        $owners = User::where('role', 'super_admin')->where('activo', true)->limit(2)->get();
        return $owners->count() === 1 ? $owners->first() : null;
    }

    public function mailReady(): bool
    {
        return app()->environment('testing') || (config('security.access_mail_ready')
            && ! in_array(config('mail.default'), ['log', 'array', null]));
    }

    public function start(Request $request): StaffAccessRequest
    {
        abort_unless($request->user()->esSuperAdmin(), 403);
        $owner = $this->owner();
        if (!$owner || !$this->mailReady()) {
            throw ValidationException::withMessages(['access'=>'El envío de correos de acceso aún no está configurado. No se ha enviado ninguna solicitud.']);
        }
        $key = 'staff-code-send:'.$request->user()->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1) || \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key.':hour', 10)) {
            throw ValidationException::withMessages(['access'=>'Espera '.$this->retryAfter($request->user()).' segundos antes de solicitar otro código. Si ya recibiste uno, puedes introducirlo abajo.']);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        \Illuminate\Support\Facades\RateLimiter::hit($key.':hour', 3600);
        $nonce = $request->session()->get('staff_access_nonce') ?? Str::random(64);
        $request->session()->put('staff_access_nonce', $nonce);
        try {
        $entry = DB::transaction(function () use ($request, $owner, $nonce) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->activo && $user->tieneRol('super_admin', 'admin', 'operador'), 403);
            StaffAccessRequest::where('user_id', $user->id)->where('session_hash', hash('sha256', $nonce))
                ->whereIn('state', ['pending','approved'])->update(['state'=>'superseded', 'code_hash'=>null]);
            $code = (string)random_int(100000, 999999);
            $entry = StaffAccessRequest::create(['id'=>(string)Str::uuid(), 'user_id'=>$user->id, 'owner_id'=>$owner->id,
                'session_hash'=>hash('sha256', $nonce), 'credential_hash'=>$this->fingerprint($user), 'owner_hash'=>$this->fingerprint($owner),
                'kind'=>$user->id === $owner->id ? 'owner_code' : 'staff_code', 'state'=>'pending',
                'code_hash'=>$code ? hash_hmac('sha256', $code, config('app.key')) : null,
                'expires_at'=>now()->addMinutes(5)]);
            // Synchronous, bounded SMTP delivery: failures roll back and never grant access.
            Notification::route('mail', $owner->email)->notify(new StaffAccessMail($entry->id, $code, $user->name, $user->id === $owner->id));
            return $entry;
        });
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            \Illuminate\Support\Facades\RateLimiter::decrement($key.':hour');
            throw $e;
        }
        $request->session()->put('staff_access_request', $entry->id);
        return $entry;
    }

    public function retryAfter(User $user): int
    {
        $key = 'staff-code-send:'.$user->id;
        $limiter = \Illuminate\Support\Facades\RateLimiter::class;
        return max(
            $limiter::tooManyAttempts($key, 1) ? $limiter::availableIn($key) : 0,
            $limiter::tooManyAttempts($key.':hour', 10) ? $limiter::availableIn($key.':hour') : 0
        );
    }

    public function current(Request $request): ?StaffAccessRequest
    {
        $entry = StaffAccessRequest::find($request->session()->get('staff_access_request'));
        return $entry && $this->bound($entry, $request) ? $entry : null;
    }

    public function bound(StaffAccessRequest $entry, Request $request): bool
    {
        return $entry->user_id === $request->user()->id
            && hash_equals($entry->session_hash, hash('sha256', (string)$request->session()->get('staff_access_nonce')))
            && hash_equals($entry->credential_hash, $this->fingerprint($request->user()));
    }

    public function validOwner(StaffAccessRequest $entry): bool
    {
        $owner = $this->owner();
        return $owner && $owner->id === $entry->owner_id && hash_equals($entry->owner_hash, $this->fingerprint($owner));
    }

    public function consume(Request $request, ?string $code = null): bool
    {
        abort_unless($request->user()->esSuperAdmin(), 403);
        return DB::transaction(function () use ($request, $code) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $entry = StaffAccessRequest::lockForUpdate()->find($request->session()->get('staff_access_request'));
            if (!$entry || !$user->activo || !$this->bound($entry, $request)
                || !hash_equals($entry->credential_hash, $this->fingerprint($user))
                || $entry->expires_at->isPast() || !$this->validOwner($entry)) { return false; }
            if (in_array($entry->kind, ['owner_code', 'staff_code'], true)) {
                if ($entry->state !== 'pending' || $entry->attempts >= 5) { return false; }
                $entry->increment('attempts');
                if (!is_string($code) || !hash_equals($entry->code_hash, hash_hmac('sha256', $code, config('app.key')))) { return false; }
            } else { return false; }
            $entry->update(['state'=>'consumed', 'consumed_at'=>now(), 'code_hash'=>null]);
            SecurityEvents::record('StaffAccess', $user->id, 'access_granted', ['session']);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put('staff_access_verified', $this->fingerprint($user));
            $request->session()->forget(['staff_access_request', 'staff_access_nonce']);
            return true;
        });
    }
}
