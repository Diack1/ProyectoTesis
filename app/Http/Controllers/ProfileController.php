<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($request->email !== $user->email) {
            $code = (string) random_int(100000, 999999);
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $code) {
                $user = \App\Models\User::lockForUpdate()->findOrFail($user->id);
                $user->forceFill(['name' => $request->name, 'pending_email' => $request->email,
                    'email_change_hash' => hash_hmac('sha256', $code, config('app.key')),
                    'email_change_expires_at' => now()->addMinutes(10), 'email_change_attempts' => 0])->save();
                \Illuminate\Support\Facades\Notification::route('mail', $request->email)
                    ->notify(new \App\Notifications\ConfirmEmailChange($code));
            });
            return Redirect::route('profile.edit')->with('status', 'email-change-pending');
        }
        $user->fill(['name' => $request->name])->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function confirmEmail(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $ok = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = \App\Models\User::lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->pending_email || ! $user->email_change_expires_at ||
                $user->email_change_expires_at->isPast() || $user->email_change_attempts >= 5) { return false; }
            $user->increment('email_change_attempts');
            if (! hash_equals($user->email_change_hash, hash_hmac('sha256', $request->code, config('app.key')))) { return false; }
            if (\App\Models\User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists()) { return false; }
            $user->forceFill(['email' => $user->pending_email, 'email_verified_at' => now(),
                'pending_email' => null, 'email_change_hash' => null, 'email_change_expires_at' => null,
                'email_change_attempts' => 0])->save();
            return true;
        });
        return $ok ? back()->with('status', 'email-change-confirmed')
            : back()->withErrors(['code' => 'Código incorrecto, vencido o agotado. Solicita un nuevo cambio desde tu perfil.']);
    }

    public function cancelEmail(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['pending_email' => null, 'email_change_hash' => null,
            'email_change_expires_at' => null, 'email_change_attempts' => 0])->save();
        return back()->with('status', 'email-change-cancelled');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        abort_unless($user->role === 'user', 403, 'Las cuentas del personal se administran desde el panel.');
        $user->forceFill(['activo' => false, 'remember_token' => null])->save();
        $user->revokeAccess();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
