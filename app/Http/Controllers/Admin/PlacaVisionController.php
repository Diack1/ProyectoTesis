<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ClientePlacaService;
use App\Services\PlacaVisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlacaVisionController extends Controller
{
    private function resultado(Request $request): ?array
    {
        $result = $request->session()->get('vision_prueba');
        if ($result && (($result['user_id'] ?? null) !== $request->user()->id || ($result['expires_at'] ?? 0) <= now()->timestamp)) {
            $request->session()->forget('vision_prueba');

            return null;
        }

        return $result;
    }

    public function index(Request $request, PlacaVisionService $vision)
    {
        return response()->view('admin.placas.index', [
            'resultado' => $this->resultado($request), 'disponible' => $vision->disponible(),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function procesarImagen(Request $request, PlacaVisionService $vision): array
    {
        $request->validate(['imagen' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000']]);
        $size = getimagesize($request->file('imagen')->getRealPath());
        if (! $size || $size[0] * $size[1] > 20000000) {
            throw ValidationException::withMessages(['imagen' => 'La imagen debe tener como máximo 20 megapíxeles.']);
        }
        $path = $request->file('imagen')->store('vision-temporal', 'local');
        abort_unless($path, 503, 'No se pudo preparar la imagen.');
        try {
            return $vision->analizar(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function analizar(Request $request, PlacaVisionService $vision)
    {
        $request->session()->forget('vision_prueba');
        $result = $this->procesarImagen($request, $vision);
        $request->session()->put('vision_prueba', [
            'id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(config('vision.session_minutes'))->timestamp,
            'analysis' => $result, 'confirmed' => null,
        ]);

        return redirect()->route('admin.placas.index');
    }

    public function camara(Request $request, PlacaVisionService $vision, ClientePlacaService $clientes)
    {
        $result = $this->procesarImagen($request, $vision);
        foreach ($result['candidates'] as &$candidate) {
            $candidate['coincidencia'] = $clientes->buscar($candidate['text']);
        }
        unset($candidate);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function cliente(Request $request, ClientePlacaService $clientes)
    {
        $request->validate(['placa' => ['required', 'string', 'max:20']]);
        $placa = strtoupper(preg_replace('/[\s-]+/', '', $request->placa));
        if (! preg_match('/\A[A-Z0-9]{5,10}\z/', $placa)) {
            throw ValidationException::withMessages(['placa' => 'Introduce una matrícula de 5 a 10 letras o números.']);
        }

        return response()->json(['placa' => $placa, 'coincidencia' => $clientes->buscar($placa)])->header('Cache-Control', 'private, no-store');
    }

    public function confirmar(Request $request)
    {
        $request->validate(['placa' => ['required', 'string', 'max:20']]);
        $request->merge(['placa' => strtoupper(preg_replace('/[\s-]+/', '', (string) $request->input('placa', '')))]);
        $data = $request->validate([
            'prueba_id' => ['required', 'uuid'], 'candidato' => ['required', 'integer', 'min:0', 'max:9'],
            'placa' => ['required', 'string', 'regex:/\A[A-Z0-9]{5,10}\z/'], 'revisado' => ['accepted'],
        ], ['placa.regex' => 'Escribe entre 5 y 10 letras o números. Puedes separar la placa con un guion.']);
        $result = $this->resultado($request);
        abort_unless($result && hash_equals($result['id'], $data['prueba_id']), 409, 'La prueba cambió o venció. Vuelve a analizar la foto.');
        abort_unless(isset($result['analysis']['candidates'][$data['candidato']]), 422, 'Selecciona una detección de esta fotografía.');
        $result['confirmed'] = ['plate' => $data['placa'], 'candidate' => (int) $data['candidato']];
        $request->session()->put('vision_prueba', $result);

        return redirect()->route('admin.placas.index')->with('success', 'Placa confirmada para esta prueba. No se ha registrado un ingreso.');
    }

    public function limpiar(Request $request)
    {
        $request->session()->forget('vision_prueba');

        return redirect()->route('admin.placas.index');
    }
}
