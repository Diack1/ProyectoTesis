<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClienteVehiculo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClienteVehiculoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['buscar' => 'nullable|string|max:100', 'editar' => 'nullable|integer']);

        return response()->view('admin.placas.clientes', [
            'vehiculos' => ClienteVehiculo::with('usuario')->when($request->filled('buscar'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('placa', 'like', '%'.$request->buscar.'%')->orWhere('nombre', 'like', '%'.$request->buscar.'%')->orWhere('telefono', 'like', '%'.$request->buscar.'%')
                    ->orWhereHas('usuario', fn ($u) => $u->where('name', 'like', '%'.$request->buscar.'%'));
            }))->orderBy('placa')->paginate(20)->withQueryString(),
            'editar' => $request->filled('editar') ? ClienteVehiculo::findOrFail($request->editar) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function guardar(Request $request, ?ClienteVehiculo $vehiculo = null)
    {
        $request->validate(['placa' => ['required', 'string', 'max:20']]);
        $request->merge(['placa' => strtoupper(preg_replace('/[\s-]+/', '', $request->placa))]);
        $data = $request->validate([
            'placa' => ['required', 'regex:/\A[A-Z0-9]{5,10}\z/', Rule::unique('cliente_vehiculos')->ignore($vehiculo?->id)],
            'nombre' => ['required', 'string', 'max:150'], 'telefono' => ['nullable', 'string', 'max:40'],
            'email_cuenta' => ['nullable', 'email', Rule::exists('users', 'email')->where('role', 'user')->where('activo', true)],
            'revisado' => ['accepted'], 'activo' => ['sometimes', 'boolean'],
        ], ['email_cuenta.exists' => 'No se encontró una cuenta de cliente activa con ese correo. Puedes dejarlo vacío para un cliente presencial.']);
        if ($vehiculo && $vehiculo->placa !== $data['placa']) {
            throw ValidationException::withMessages(['placa' => 'La placa no se cambia en una ficha existente. Desactiva la ficha incorrecta y registra la correcta.']);
        }
        $user = empty($data['email_cuenta']) ? null : User::where('email', $data['email_cuenta'])->where('role', 'user')->where('activo', true)->firstOrFail();
        $values = ['placa' => $data['placa'], 'nombre' => $user?->name ?? $data['nombre'], 'telefono' => $data['telefono'] ?? null,
            'user_id' => $user?->id, 'activo' => $request->boolean('activo', true), 'registrado_por' => $request->user()->id];
        $vehiculo ? $vehiculo->update($values) : ClienteVehiculo::create($values);

        return redirect()->route('admin.clientes-vehiculos.index')->with('success', 'Asociación de cliente y vehículo guardada.');
    }
}
