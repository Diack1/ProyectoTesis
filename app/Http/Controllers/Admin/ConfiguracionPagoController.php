<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionPagoController extends Controller
{
    public function edit()
    {
        return view('admin.pagos.configuracion', ['configuracion' => ConfiguracionPago::actual()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['titular' => 'required|string|max:255', 'telefono' => 'required|string|max:30',
            'minutos_pago' => 'required|integer|min:5|max:60', 'tolerancia_llegada' => 'sometimes|required|integer|min:10|max:15', 'qr_yape' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'qr_plin' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096']);
        foreach (['qr_yape', 'qr_plin'] as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('qr-negocio', 'local');
                abort_unless($data[$field], 500, 'No se pudo guardar el QR.');
            }
        }
        ConfiguracionPago::actual()->fill($data)->save();

        return back()->with('success', 'Datos de cobro actualizados.');
    }

    public function qr(string $metodo)
    {
        abort_unless(in_array($metodo, ['yape', 'plin']), 404);
        $path = ConfiguracionPago::actual()->{'qr_'.$metodo};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store']);
    }
}
