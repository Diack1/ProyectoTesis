<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['buscar' => 'nullable|string|max:100', 'estado' => 'nullable|in:activos,inactivos']);
        $personal = User::whereIn('role', ['admin', 'super_admin', 'operador']);
        $totalPersonal = (clone $personal)->count();
        $activos = (clone $personal)->where('activo', true)->count();
        $administradores = (clone $personal)->where('role', 'admin')->count();
        $admins = User::whereIn('role', ['admin', 'super_admin', 'operador'])
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->buscar.'%')->orWhere('email', 'like', '%'.$request->buscar.'%')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activos'))
            ->orderBy('role', 'desc')
            ->orderBy('name')
            ->paginate(10)->withQueryString();

        return view('superadmin.admins.index', compact('admins', 'totalPersonal', 'activos', 'administradores'));
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
            'role' => 'required|in:admin',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Administrador creado correctamente.');
    }

    public function toggleActivo(User $user)
    {
        if (! in_array($user->role, ['admin', 'operador']) || ($user->role === 'admin' && auth()->user()->role !== 'super_admin')) {
            return redirect()
                ->route('superadmin.dashboard')
                ->with('error', 'Solo se puede activar o desactivar cuentas de administrador.');
        }

        $user->update([
            'activo' => ! $user->activo,
        ]);

        if (! $user->activo) {
            $user->forceFill(['remember_token' => null])->save();
            $user->revokeAccess();
        }

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Estado del administrador actualizado correctamente.');
    }
}
