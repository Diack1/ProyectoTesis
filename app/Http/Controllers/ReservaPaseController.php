<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Services\LiquidacionService;
use App\Services\ReservaService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

class ReservaPaseController extends Controller
{
    public function show(Reserva $reserva, ReservaService $service)
    {
        abort_unless($reserva->user_id === auth()->id(), 403);
        abort_unless($reserva->pago_al_llegar, 404);
        $service->expirarReservasPendientes();
        $reserva->refresh()->load('espacio', 'estadia');
        $qr = null;
        if ($reserva->estado === 'pendiente_pago') {
            $url = URL::signedRoute('admin.reservas.escanear', ['reserva' => $reserva->id]);
            $qr = base64_encode((new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd())))->writeString($url));
        }
        return response()->view('reservas.pase', compact('reserva', 'qr'))->header('Cache-Control', 'no-store');
    }

    public function escanear(Reserva $reserva)
    {
        abort_unless($reserva->pago_al_llegar, 404);
        // Auth, staff role, MFA and signature middleware protect this read-only URL.
        if ($reserva->estadia) {
            return redirect()->route('admin.estadias.show', $reserva->estadia);
        }
        return redirect()->route('admin.estadias.create', ['reserva' => $reserva->id]);
    }

    public function tiempo(Reserva $reserva, LiquidacionService $service)
    {
        abort_unless($reserva->user_id === auth()->id(), 403);
        $estadia = $reserva->estadia;
        $corte = $estadia?->liquidado_hasta ?? $estadia?->hora_salida ?? now();
        $calculo = $estadia?->inicio_cobro ? $service->calcular($estadia, $corte) : null;
        return response()->json([
            'segundos' => $estadia?->inicio_cobro ? max(0, (int) $estadia->inicio_cobro->diffInSeconds($corte)) : 0,
            'en_curso' => (bool) ($estadia?->inicio_cobro && ! $estadia->hora_salida),
            'estado' => $estadia ? ($estadia->hora_salida ? 'Salida registrada' : ($estadia->inicio_cobro ? 'Vehículo estacionado' : 'Esperando inicio del sensor')) : 'Esperando entrada',
            'extra_centavos' => $calculo['exceso_centavos'] ?? 0,
        ])->header('Cache-Control', 'no-store');
    }
}
