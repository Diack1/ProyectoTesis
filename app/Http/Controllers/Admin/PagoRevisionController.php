<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionPago;
use App\Models\Pago;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoRevisionController extends Controller
{
    public function index()
    {
        return view('admin.pagos.index', ['pagos' => Pago::with('reserva.espacio', 'usuario')
            ->whereNotNull('enviado_at')->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->latest('enviado_at')->paginate(20)]);
    }

    public function pendientes()
    {
        return response()->json(['cantidad' => Pago::where('estado', 'pendiente')->whereNotNull('enviado_at')->count()]);
    }

    public function revisar(Request $request, Pago $pago)
    {
        $data = $request->validate(['decision' => 'required|in:aprobado,rechazado',
            'motivo_revision' => 'nullable|required_if:decision,rechazado|string|max:1000']);
        DB::transaction(function () use ($pago, $data, $request) {
            $reserva = Reserva::lockForUpdate()->findOrFail($pago->reserva_id);
            $pago = Pago::lockForUpdate()->findOrFail($pago->id);
            abort_unless($reserva->estado === 'pendiente_pago' && $pago->estado === 'pendiente' && $pago->enviado_at, 409, 'Este pago ya no admite revisión.');
            $pago->update(['estado' => $data['decision'], 'revisado_por' => $request->user()->id, 'revisado_at' => now(),
                'motivo_revision' => $data['motivo_revision'] ?? null, 'pagado_at' => $data['decision'] === 'aprobado' ? now() : null,
                'operacion_unica' => $data['decision'] === 'rechazado' ? null : $pago->operacion_unica]);
            $reserva->update($data['decision'] === 'aprobado'
             ? ['estado' => 'confirmada', 'pagado_at' => now(), 'expires_at' => null]
             : ['expires_at' => now()->addMinutes(ConfiguracionPago::actual()->minutos_pago)]);
        });

        return back()->with('success', 'Revisión guardada. El cliente puede consultar el resultado en su reserva.');
    }
}
