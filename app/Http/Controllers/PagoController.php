<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionPago;
use App\Models\Pago;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoController extends Controller
{
    public function show(Reserva $reserva, ReservaService $service)
    {
        abort_unless($reserva->user_id === Auth::id(), 403);
        $service->expirarReservasPendientes();
        $reserva->refresh()->load('espacio', 'pagos');

        return response()->view('pagos.show', ['reserva' => $reserva, 'pago' => $reserva->pagos->sortByDesc('id')->first(),
            'configuracion' => ConfiguracionPago::actual()])->header('Cache-Control', 'no-store');
    }

    public function enviar(Request $request, Reserva $reserva)
    {
        abort_unless($reserva->user_id === Auth::id(), 403);
        $data = $request->validate(['metodo_pago' => 'required|in:yape,plin',
            'referencia_pago' => 'nullable|required_without:comprobante|string|max:80|regex:/^[A-Za-z0-9-]+$/',
            'comprobante' => 'nullable|required_without:referencia_pago|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $reserva, $data, &$path) {
                $reserva = Reserva::lockForUpdate()->findOrFail($reserva->id);
                if ($reserva->estado !== 'pendiente_pago' || ! $reserva->expires_at || now()->gt($reserva->expires_at)) {
                    throw ValidationException::withMessages(['pago' => 'La reserva no admite pagos o el plazo terminó.']);
                }
                if ($reserva->pagos()->where('estado', 'pendiente')->whereNotNull('enviado_at')->exists()) {
                    throw ValidationException::withMessages(['pago' => 'Tu pago ya está pendiente de revisión.']);
                }
                if (! ConfiguracionPago::actual()->{'qr_'.$data['metodo_pago']}) {
                    throw ValidationException::withMessages(['pago' => 'El negocio aún no configuró este medio de pago.']);
                }
                $key = empty($data['referencia_pago']) ? null : $data['metodo_pago'].':'.strtoupper($data['referencia_pago']);
                if ($key && Pago::where('operacion_unica', $key)->exists()) {
                    throw ValidationException::withMessages(['referencia_pago' => 'Esta operación ya fue presentada. Comunícate con el negocio.']);
                }
                if ($request->hasFile('comprobante')) {
                    $path = $request->file('comprobante')->store('comprobantes', 'local');
                    abort_unless($path, 500);
                }
                $values = ['metodo_pago' => $data['metodo_pago'], 'referencia_pago' => $data['referencia_pago'] ?? null,
                    'operacion_unica' => $key, 'comprobante' => $path, 'enviado_at' => now(), 'monto' => $reserva->monto_total, 'estado' => 'pendiente'];
                $pago = $reserva->pagos()->where('estado', 'pendiente')->whereNull('enviado_at')->first();
                if ($pago) {
                    $pago->update($values);
                } else {
                    $reserva->pagos()->create($values + ['user_id' => Auth::id(), 'codigo_pago' => 'PAG-'.Str::uuid()]);
                }
                $reserva->update(['expires_at' => null]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($e instanceof \Illuminate\Database\UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['referencia_pago' => 'Esta operación ya fue presentada. Comunícate con el negocio.']);
            }
            throw $e;
        }

        return back()->with('success', 'Pago enviado. El personal verificará el abono antes de confirmar la reserva.');
    }

    public function comprobante(Pago $pago)
    {
        abort_unless(Auth::id() === $pago->user_id || Auth::user()->tieneRol('admin', 'super_admin', 'operador'), 403);
        abort_unless($pago->comprobante && Storage::disk('local')->exists($pago->comprobante), 404);

        return Storage::disk('local')->response($pago->comprobante, null, ['Cache-Control' => 'private, no-store']);
    }
}

