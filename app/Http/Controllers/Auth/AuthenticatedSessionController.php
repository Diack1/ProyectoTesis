<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->activo) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta está desactivada. Comunícate con el administrador.',
                ]);
        }

        $request->session()->forget(['mfa_verified', 'mfa_setup', 'mfa_recovery_display', 'staff_access_verified', 'staff_access_nonce', 'staff_access_request']);
        if (app(\App\Services\StaffAccessService::class)->required($user)) {
            $access = app(\App\Services\StaffAccessService::class);
            if ($access->mailReady()) {
                try { $access->start($request); }
                catch (\Illuminate\Validation\ValidationException $e) {
                    return redirect()->route('staff-access.show')->withErrors($e->errors());
                } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                    return redirect()->route('staff-access.show')->withErrors(['access'=>\App\Services\MailDeliveryIssue::message($e)]);
                }
            }

            return redirect()->route(app(\App\Services\StaffAccessService::class)->entryRoute());
        }

        if ($user->tieneRol('admin', 'operador') && !$user->hasVerifiedEmail()) {
            try { app(\App\Services\CustomerVerificationService::class)->send($user); }
            catch (\Illuminate\Validation\ValidationException $e) { return redirect()->route('verification.notice')->withErrors($e->errors()); }
            catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) { return redirect()->route('verification.notice')->withErrors(['code'=>\App\Services\MailDeliveryIssue::message($e)]); }
            return redirect()->route('verification.notice');
        }

        if ($user->role === 'super_admin') {
            return redirect()->route('admin.dashboard');
        }

        if (in_array($user->role, ['admin', 'operador'])) {
            return redirect()->route('admin.dashboard');
        }

        if (!$user->hasVerifiedEmail()) { return redirect()->route('verification.notice'); }
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
