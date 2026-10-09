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
        $query = $this->consulta($request);

        $registros = $query->paginate(15)->withQueryString();

        $resumenQuery = $this->consulta($request);

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
        $query = $this->consulta($request);

        $fileName = 'reporte_ocupacion_parkeo.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fwrite($handle, "sep=;\r\n");

            fputcsv($handle, [
                'ID',
                'Fecha y hora',
                'Espacio',
                'Sensor',
                'Estado detectado',
                'Distancia cm',
                'Origen',
                'Fecha de registro',
            ], ';');

            $query->chunk(100, function ($registros) use ($handle) {
                foreach ($registros as $registro) {
                    fputcsv($handle, [
                        $registro->id,
                        $registro->fecha_hora,
                        self::csvText($registro->espacio->codigo ?? '-'),
                        self::csvText($registro->sensor->codigo_sensor ?? '-'),
                        $registro->estado_detectado,
                        $registro->distancia_cm,
                        self::csvText($registro->origen),
                        $registro->created_at,
                    ], ';');
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
    public function exportarExcel(Request $request, \App\Services\ReporteExcelService $excel): StreamedResponse
    {
        $query = $this->consulta($request);
        $filtros = 'Desde: '.($request->fecha_inicio ?: 'Sin límite').' · Hasta: '.($request->fecha_fin ?: 'Sin límite')
            .' · Estado: '.($request->estado ?: 'Todos').' · Espacio: '.($request->filled('espacio_id') ? Espacio::find($request->espacio_id)?->codigo : 'Todos');
        // Finish the workbook before sending download headers so failures are logged normally.
        $path = tempnam(sys_get_temp_dir(), 'parkeo-report-');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del reporte.');
        }
        try {
            $excel->guardar($path, $query, $filtros);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, 'Parkeo-ocupacion-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function consulta(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d',
            'estado' => 'nullable|in:libre,ocupado',
            'espacio_id' => 'nullable|integer|exists:espacios,id',
        ]);
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

        return $query->orderByDesc('id');
    }

    public static function csvText(?string $value): string
    {
        $value ??= '';
        return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
