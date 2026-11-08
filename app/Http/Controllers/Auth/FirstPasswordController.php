<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class FirstPasswordController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->user()->must_change_password) return redirect()->route('dashboard');
        return view('auth.first-password');
    }

    public function update(Request $request)
    {
        if (! $request->user()->must_change_password) return redirect()->route('dashboard');
        $data = $request->validate(['current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual es incorrecta.']);
        }
        if (Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Elige una contraseña diferente a la inicial.']);
        }
        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        $request->session()->regenerate();
        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada.');
    }
}
