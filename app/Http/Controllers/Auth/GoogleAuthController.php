<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect'), 404);
        $callback = parse_url(config('services.google.redirect'));
        $port = $callback['port'] ?? (($callback['scheme'] ?? '') === 'https' ? 443 : 80);
        if (($callback['host'] ?? '') !== $request->getHost()
            || (!app()->environment('production') && $port !== $request->getPort())) {
            return redirect()->route('login')->withErrors(['google' => 'El acceso con Google no está configurado para esta dirección de Parke’o. El administrador debe configurar la URL de retorno de este sitio. Puedes utilizar tu correo y contraseña.']);
        }
        $request->session()->forget('google_link');
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect'), 404);
        try {
            // Socialite validates OAuth state against this browser's session.
            $identity = Socialite::driver('google')->user();
            abort_unless($identity->getId() && filter_var($identity->getEmail(), FILTER_VALIDATE_EMAIL)
                && ($identity->user['email_verified'] ?? $identity->user['verified_email'] ?? false) === true, 403);
            $email = Str::lower($identity->getEmail());
            $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing && !$existing->esUsuario()) {
                $request->session()->forget('google_link');
                return redirect()->route('login')->withErrors(['google' => 'Las cuentas del personal y del propietario acceden con su correo y contraseña de Parke’o. El propietario también debe confirmar su código de acceso. Google está disponible para cuentas de clientes.']);
            }
            $user = DB::transaction(function () use ($identity, $email) {
                $user = User::where('google_id', $identity->getId())->lockForUpdate()->first();
                if ($user) {
                    // A changed contact email must be verified through the local account flow.
                    abort_unless($user->activo && $user->esUsuario() && $user->email === $email, 403);
                    return $user;
                }
                if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    return null; // Never silently link an existing account by matching email alone.
                }
                $user = User::create(['name' => Str::limit($identity->getName() ?: 'Cliente', 255, ''),
                    'email' => $email, 'password' => Str::random(64), 'role' => 'user', 'activo' => true]);
                // Google is authoritative for Gmail / verified Workspace accounts.
                $authoritative = str_ends_with($email, '@gmail.com') || !empty($identity->user['hd']);
                $user->forceFill(['google_id' => $identity->getId(), 'email_verified_at' => $authoritative ? now() : null])->save();
                return $user;
            });
            if (!$user) {
                $request->session()->put('google_link', ['sub' => $identity->getId(), 'email' => $email, 'expires' => now()->addMinutes(5)->timestamp]);
                return redirect()->route('google.link');
            }
            Auth::login($user);
            $request->session()->regenerate();
            return $user->hasVerifiedEmail() ? redirect()->intended(route('reservas.index')) : redirect()->route('verification.notice');
        } catch (\Throwable $e) {
            // Do not log OAuth codes, access tokens or provider response bodies.
            return redirect()->route('login')->withErrors(['google' => 'No se pudo completar el acceso con Google. Inténtalo de nuevo o utiliza tu correo y contraseña.']);
        }
    }

    public function linkForm(Request $request)
    {
        abort_unless($this->pending($request), 403);
        return response()->view('auth.link-google')->header('Cache-Control', 'private, no-store');
    }

    public function link(Request $request)
    {
        $pending = $this->pending($request);
        abort_unless($pending, 403);
        $data = $request->validate(['password' => 'required|string']);
        $user = DB::transaction(function () use ($pending, $data) {
            $user = User::where('email', $pending['email'])->lockForUpdate()->first();
            if (!$user || !$user->activo || !$user->esUsuario() || !$user->hasVerifiedEmail()
                || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)
                || ($user->google_id && $user->google_id !== $pending['sub'])) {
                return null;
            }
            $user->forceFill(['google_id' => $pending['sub']])->save();
            return $user;
        });
        if (!$user) { return back()->withErrors(['password' => 'No se pudo vincular. Verifica tu contraseña y que tu cuenta cliente esté activada.']); }
        $request->session()->forget('google_link');
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended(route('reservas.index'));
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('google_link');
        return is_array($pending) && ($pending['expires'] ?? 0) > now()->timestamp ? $pending : null;
    }
}
