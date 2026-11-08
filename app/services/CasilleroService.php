<?php

namespace App\Services;

use App\Models\ConfiguracionPago;
use App\Models\Estadia;
use Illuminate\Validation\ValidationException;

class CasilleroService
{
    public function opciones(): array
    {
        $cantidad = (int) (ConfiguracionPago::actual()->cantidad_casilleros ?? 12);
        $ocupados = Estadia::activas()->where('llave_recibida', true)
            ->whereNotNull('ubicacion_llave')->pluck('ubicacion_llave')->all();
        return collect(range(1, $cantidad))->map(fn ($numero) => [
            'nombre' => 'Casillero '.$numero,
            'ocupado' => in_array('Casillero '.$numero, $ocupados, true),
        ])->all();
    }

    public function validarIngreso(array $data): ?string
    {
        if (empty($data['llave_recibida'])) return null;
        // A single configuration row serializes assignments across different parking spaces.
        $configuracion = ConfiguracionPago::query()->lockForUpdate()->first();
        if (!$configuracion) $configuracion = ConfiguracionPago::create([])->refresh();
        $nombre = $data['ubicacion_llave'] ?? '';
        if (!preg_match('/^Casillero ([1-9][0-9]*)$/', $nombre, $partes)
            || (int) $partes[1] > (int) $configuracion->cantidad_casilleros) {
            throw ValidationException::withMessages(['ubicacion_llave' => 'Selecciona un casillero configurado.']);
        }
        if (Estadia::activas()->where('llave_recibida', true)->where('ubicacion_llave', $nombre)->exists()) {
            throw ValidationException::withMessages(['ubicacion_llave' => 'Ese casillero está ocupado. Actualiza el formulario y elige otro.']);
        }
        return $nombre;
    }
}
