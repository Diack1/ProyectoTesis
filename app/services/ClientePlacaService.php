<?php

namespace App\Services;

use App\Models\ClienteVehiculo;
use App\Models\Estadia;
use App\Models\Reserva;

class ClientePlacaService
{
    public function buscar(string $placa): array
    {
        $placa = strtoupper(preg_replace('/[\s-]+/', '', $placa));
        if (! preg_match('/\A[A-Z0-9]{5,10}\z/', $placa)) {
            return ['registrado' => false, 'visitas' => 0, 'frecuente' => false, 'cliente' => null, 'reservas' => [], 'estadia_activa' => null];
        }
        $vehiculo = ClienteVehiculo::with('usuario')->where('placa', $placa)->where('activo', true)->first();
        $visitas = Estadia::where('placa', $placa)->whereNotNull('hora_salida')->count();
        $activa = Estadia::where('placa', $placa)->activas()->first();
        $ahora = now();
        $reservas = Reserva::with(['usuario', 'espacio'])->where('placa', $placa)
            ->where('estado', 'confirmada')->whereNull('inasistencia_at')
            ->whereHas('pagoAprobado')->whereDoesntHave('estadia')
            ->whereDate('fecha_reserva', '>=', $ahora->copy()->subDay()->toDateString())
            ->whereDate('fecha_reserva', '<=', $ahora->toDateString())
            ->orderBy('fecha_reserva')->orderBy('hora_inicio')->get()
            ->filter(fn ($r) => $r->limite_llegada->gte($ahora))->take(5)
            ->map(fn ($r) => ['codigo' => $r->codigo_reserva, 'placa' => $r->placa,
                'cliente' => $r->usuario?->name, 'espacio' => $r->espacio?->codigo,
                'llegada' => $r->fecha_reserva->format('d/m/Y').' '.substr($r->hora_inicio, 0, 5),
                'url' => route('admin.estadias.create', ['reserva' => $r->id, 'placa' => $placa])])->values()->all();

        return [
            'registrado' => (bool) $vehiculo, 'visitas' => $visitas, 'frecuente' => $visitas >= 2,
            'cliente' => $vehiculo ? ['nombre' => $vehiculo->usuario?->name ?? $vehiculo->nombre,
                'telefono' => $vehiculo->telefono, 'email' => $vehiculo->usuario?->activo ? $vehiculo->usuario->email : null] : null,
            'reservas' => $reservas,
            'estadia_activa' => $activa ? ['ticket' => $activa->codigo_ticket, 'url' => route('admin.estadias.show', $activa)] : null,
            'ingreso_url' => route('admin.estadias.create', ['placa' => $placa]),
        ];
    }
}
