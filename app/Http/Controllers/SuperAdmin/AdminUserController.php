<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const STAFF = ['super_admin', 'junior', 'admin', 'operador'];

    private function passwordRule(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }

    public function index(Request $request)
    {
        $request->validate(['buscar' => 'nullable|string|max:100', 'estado' => 'nullable|in:activos,inactivos']);
        $personal = User::whereIn('role', self::STAFF);
        return view('superadmin.admins.index', [
            'totalPersonal' => (clone $personal)->count(),
            'activos' => (clone $personal)->where('activo', true)->count(),
            'administradores' => (clone $personal)->where('role', 'super_admin')->count(),
            'admins' => $personal->when($request->filled('buscar'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->buscar.'%')->orWhere('email', 'like', '%'.$request->buscar.'%')))
                ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activos'))
                ->orderByRaw("CASE WHEN role = 'super_admin' THEN 0 ELSE 1 END")->orderBy('name')->paginate(10)->withQueryString(),
        ]);
    }

    public function create() { return view('superadmin.admins.create'); }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', $this->passwordRule()], 'role' => 'required|in:super_admin,junior', 'activo' => 'sometimes|boolean']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'role' => $data['role'], 'activo' => $request->boolean('activo'), 'must_change_password' => false]);
        $user->forceFill(['email_verified_at' => now()])->save();
        return redirect()->route('superadmin.dashboard')->with('success', 'Usuario creado. Puede ingresar con la contraseña asignada.');
    }

    public function edit(User $user)
    {
        abort_unless(in_array($user->role, self::STAFF, true), 404);
        return view('superadmin.admins.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless(in_array($user->role, self::STAFF, true), 404);
        $data = $request->validate(['name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => 'required|in:super_admin,junior']);
        DB::transaction(function () use ($user, $data, $request) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if ($locked->role === 'super_admin' && $data['role'] !== 'super_admin') $this->ultimoAdministrador($locked);
            if ($locked->id === $request->user()->id && $data['role'] !== 'super_admin') {
                throw ValidationException::withMessages(['role' => 'No puedes quitarte el rol administrador durante esta sesión.']);
            }
            $emailCambio = $locked->email !== $data['email'];
            $roleCambio = $locked->role !== $data['role'];
            $locked->update($data);
            if ($emailCambio) $locked->forceFill(['email_verified_at' => now()])->save();
            if ($emailCambio || $roleCambio) $locked->revokeAccess();
        });
        return redirect()->route('superadmin.dashboard')->with('success', 'Usuario actualizado.');
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_unless(in_array($user->role, self::STAFF, true), 404);
        $data = $request->validate(['password' => ['required', 'confirmed', $this->passwordRule()]]);
        DB::transaction(function () use ($user, $data) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $locked->update(['password' => $data['password'], 'must_change_password' => false]);
            $locked->forceFill(['remember_token' => null])->save();
            $locked->revokeAccess();
        });
        return redirect()->route('superadmin.dashboard')->with('success', 'Contraseña restablecida. El usuario puede ingresar con la nueva contraseña.');
    }

    public function toggleActivo(Request $request, User $user)
    {
        abort_unless(in_array($user->role, self::STAFF, true), 404);
        DB::transaction(function () use ($request, $user) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if ($locked->activo && $locked->role === 'super_admin') $this->ultimoAdministrador($locked);
            if ($locked->id === $request->user()->id && $locked->activo) {
                throw ValidationException::withMessages(['usuario' => 'No puedes desactivar tu propia sesión.']);
            }
            $locked->update(['activo' => ! $locked->activo]);
            if (! $locked->activo) { $locked->forceFill(['remember_token' => null])->save(); $locked->revokeAccess(); }
        });
        return redirect()->route('superadmin.dashboard')->with('success', 'Estado del usuario actualizado.');
    }

    private function ultimoAdministrador(User $user): void
    {
        if ($user->activo && User::where('role', 'super_admin')->where('activo', true)->lockForUpdate()->pluck('id')->count() <= 1) {
            throw ValidationException::withMessages(['usuario' => 'Debe permanecer al menos un administrador activo.']);
        }
    }
}
