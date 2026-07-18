<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index()
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])
            ->orderBy('role', 'desc')
            ->orderBy('name')
            ->paginate(10);

        return view('superadmin.admins.index', compact('admins'));
    }

    public function create()
    {
        return view('superadmin.admins.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'activo' => true,
        ]);

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Administrador creado correctamente.');
    }

    public function toggleActivo(User $user)
    {
        if ($user->role !== 'admin') {
            return redirect()
                ->route('superadmin.dashboard')
                ->with('error', 'Solo se puede activar o desactivar cuentas de administrador.');
        }

        $user->update([
            'activo' => !$user->activo,
        ]);

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Estado del administrador actualizado correctamente.');
    }
}
