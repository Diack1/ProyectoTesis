<?php
namespace App\Services;

use App\Models\{Abonado, Abono, Espacio, Estadia, Pago, User, Tarifa, ClienteCobro};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AbonoService {
    public function registrar(array $data, User $staff): Abono
    {
        return DB::transaction(function () use ($data, $staff) {
            $space = Espacio::lockForUpdate()->findOrFail($data['espacio_id']);
            $n = PlanoEspacios::numero($space->codigo);
            if (!$space->activo || $n < 1 || $n > 41) { $this->error('Selecciona un espacio activo del plano.'); }
            $inicio = Carbon::parse($data['inicio'])->startOfDay();
            $meses = (int) ($data['meses'] ?? 1);
            if ($meses < 1 || $meses > 4) { $this->error('Selecciona entre 1 y 4 meses.'); }
            $fin = $inicio->copy()->addMonthsNoOverflow($meses);
            if ($fin->lte(now())) { $this->error('El periodo debe ser actual o futuro.'); }
            if (Abono::where('espacio_id', $space->id)->where('inicio','<',$fin)->where('fin','>',$inicio)->exists()) {
                $this->error('Este espacio ya tiene un abono que coincide con el periodo.');
            }
            if ($space->reservas()->whereIn('estado',['pendiente_pago','confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia')->exists()) {
                $this->error('Resuelve primero las reservas pendientes de llegada de este espacio.');
            }
            $cliente = empty($data['abonado_id']) ? null : Abonado::lockForUpdate()->findOrFail($data['abonado_id']);
            $active = $space->estadias()->activas()->first();
            if (!$active && in_array($space->getRawOriginal('estado_actual'), ['ocupado','mantenimiento'])) { $this->error('Revisa el estado físico del espacio antes de asignarlo.'); }
            if ($active && $active->abono_id && (!$cliente || $active->abono?->abonado_id !== $cliente->id)) { $this->error('Este espacio ya está ocupado por otro abonado.'); }
            if (!$cliente) {
                $cliente = Abonado::create(['nombre'=>$data['nombre'], 'telefono'=>$data['telefono'] ?? null,
                    'dni'=>$data['dni'] ?? null, 'placas'=>$data['placas']]);
            } elseif ((!$cliente->dni && !empty($data['dni'])) || (!$cliente->telefono && !empty($data['telefono']))) {
                $cliente->update(['dni'=>$cliente->dni ?: ($data['dni'] ?? null),
                    'telefono'=>$cliente->telefono ?: ($data['telefono'] ?? null)]);
            }
            $tarifa = isset($data['tarifa_id']) ? Tarifa::where('activo',true)->where('tipo_tarifa','mensual')->find($data['tarifa_id']) : null;
            if (isset($data['tarifa_id']) && (!$tarifa || (float)$tarifa->monto_base <= 0 || !$space->vehiculoTipos()->where('vehiculo_tipos.id',$tarifa->vehiculo_tipo_id)->exists())) {
                $this->error('Selecciona una tarifa mensual activa compatible con el espacio.');
            }
            $base = $tarifa ? LiquidacionService::centavos($tarifa->monto_base) * $meses : ($n <= 26 ? 27000 : 24000) * $meses;
            if (isset($data['monto_base_centavos']) && (int) $data['monto_base_centavos'] !== $base) {
                $this->error('La tarifa cambió. Vuelve al formulario para actualizar el importe.');
            }
            $cobro = app(CobroPresencialService::class);
            $importe = $cobro->importe($base,$data);
            $clienteCobro = isset($data['cliente_cobro_id']) ? ClienteCobro::findOrFail($data['cliente_cobro_id']) : null;
            if (($data['tipo_comprobante'] ?? 'boleta') === 'factura' && $clienteCobro?->tipo_documento !== 'RUC') {
                $this->error('La factura requiere un cliente con RUC.');
            }
            if (!$clienteCobro && $cliente->dni) {
                $partes = explode(' ', trim($cliente->nombre), 2);
                $celular=preg_replace('/\D/','',(string)$cliente->telefono);
                $clienteCobro = ClienteCobro::updateOrCreate(['numero_documento'=>$cliente->dni],
                    ['nombres'=>$partes[0], 'apellidos'=>$partes[1] ?? $partes[0], 'celular'=>strlen($celular)>=9 && strlen($celular)<=15 ? $celular : null,
                        'tipo_documento'=>'DNI']);
            }
            $abono = Abono::create(['abonado_id'=>$cliente->id, 'espacio_id'=>$space->id, 'inicio'=>$inicio, 'fin'=>$fin,
                'zona'=>$n <= 26 ? 'techo' : 'patio', 'monto'=>$importe['total']/100, 'monto_base'=>$base/100,
                'meses'=>$meses, 'tarifa_id'=>$tarifa?->id, 'cliente_cobro_id'=>$clienteCobro?->id,
                'espacio_contratado_codigo'=>$space->codigo, 'tarifa_contratada_nombre'=>$tarifa?->nombre,
                'descuento_porcentaje'=>$importe['porcentaje']/100, 'descuento_monto'=>$importe['fijo']/100,
                'motivo_descuento'=>$data['motivo_descuento'] ?? null,
                'tipo_comprobante'=>$data['tipo_comprobante'] ?? 'boleta', 'tipo_impresion'=>$data['tipo_impresion'] ?? 'detallado',
                'registrado_por'=>$staff->id]);
            $resultado = $cobro->registrar($data,$importe['total'],$staff,['abono_id'=>$abono->id]);
            if ($importe['total']===0) Pago::create(['abono_id'=>$abono->id,'caja_apertura_id'=>app(CajaService::class)->abierta(true)->id,'codigo_pago'=>'CAJA-'.Str::uuid(),
                'metodo_pago'=>'manual','monto'=>0,'estado'=>'aprobado','pagado_at'=>now(),
                'revisado_at'=>now(),'revisado_por'=>$staff->id,'motivo_revision'=>'Abono cubierto por descuento total.']);
            $abono->update(['efectivo_recibido'=>$resultado['recibido']===null?null:$resultado['recibido']/100,
                'vuelto'=>$resultado['vuelto']===null?null:$resultado['vuelto']/100]);
            app(VentaService::class)->desdeOrigen('abono', $abono->id);
            return $abono;
        });
    }
    private function error(string $message): never { throw ValidationException::withMessages(['abono'=>$message]); }
}
