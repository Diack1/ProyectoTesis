<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\RegistroOcupacion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $query = RegistroOcupacion::with(['espacio', 'sensor'])
            ->orderBy('fecha_hora', 'desc');

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_hora', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_hora', '<=', $request->fecha_fin);
        }

        if ($request->filled('estado')) {
            $query->where('estado_detectado', $request->estado);
        }

        if ($request->filled('espacio_id')) {
            $query->where('espacio_id', $request->espacio_id);
        }

        $registros = $query->paginate(15)->withQueryString();

        $resumenQuery = RegistroOcupacion::query();

        if ($request->filled('fecha_inicio')) {
            $resumenQuery->whereDate('fecha_hora', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $resumenQuery->whereDate('fecha_hora', '<=', $request->fecha_fin);
        }

        if ($request->filled('estado')) {
            $resumenQuery->where('estado_detectado', $request->estado);
        }

        if ($request->filled('espacio_id')) {
            $resumenQuery->where('espacio_id', $request->espacio_id);
        }

        $totalRegistros = (clone $resumenQuery)->count();
        $totalLibres = (clone $resumenQuery)->where('estado_detectado', 'libre')->count();
        $totalOcupados = (clone $resumenQuery)->where('estado_detectado', 'ocupado')->count();

        $espacios = Espacio::orderBy('codigo')->get();

        return view('reportes.index', compact(
            'registros',
            'espacios',
            'totalRegistros',
            'totalLibres',
            'totalOcupados'
        ));
    }

    public function exportarCsv(Request $request): StreamedResponse
    {
        $query = RegistroOcupacion::with(['espacio', 'sensor'])
            ->orderBy('fecha_hora', 'desc');

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_hora', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_hora', '<=', $request->fecha_fin);
        }

        if ($request->filled('estado')) {
            $query->where('estado_detectado', $request->estado);
        }

        if ($request->filled('espacio_id')) {
            $query->where('espacio_id', $request->espacio_id);
        }

        $fileName = 'reporte_ocupacion_cochera_tentacion.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Fecha y hora',
                'Espacio',
                'Sensor',
                'Estado detectado',
                'Distancia cm',
                'Origen',
                'Fecha de registro'
            ], ';');

            $query->chunk(100, function ($registros) use ($handle) {
                foreach ($registros as $registro) {
                    fputcsv($handle, [
                        $registro->id,
                        $registro->fecha_hora,
                        $registro->espacio->codigo ?? '-',
                        $registro->sensor->codigo_sensor ?? '-',
                        $registro->estado_detectado,
                        $registro->distancia_cm,
                        $registro->origen,
                        $registro->created_at,
                    ], ';');
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}