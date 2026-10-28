<?php
namespace App\Services;

use App\Models\{Abonado, Abono, Espacio, Estadia, Pago, User};
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
            $fin = $inicio->copy()->addMonthNoOverflow();
            if ($fin->lte(now())) { $this->error('El periodo debe ser actual o futuro.'); }
            if (Abono::where('espacio_id', $space->id)->where('inicio','<',$fin)->where('fin','>',$inicio)->exists()) {
                $this->error('Este espacio ya tiene un abono que coincide con el periodo.');
            }
            if ($space->reservas()->whereIn('estado',['pendiente_pago','confirmada'])->whereDoesntHave('estadia')->exists()) {
                $this->error('Resuelve primero las reservas pendientes de llegada de este espacio.');
            }
            $cliente = empty($data['abonado_id']) ? null : Abonado::lockForUpdate()->findOrFail($data['abonado_id']);
            $active = $space->estadias()->activas()->first();
            if ($active && (!$cliente || $active->abono?->abonado_id !== $cliente->id)) {
                $this->error('El espacio está ocupado. Libéralo antes de asignar el abono.');
            }
            if (!$active && in_array($space->getRawOriginal('estado_actual'), ['ocupado','mantenimiento'])) { $this->error('Revisa el estado físico del espacio antes de asignarlo.'); }
            if (!$cliente) {
                $cliente = Abonado::create(['nombre'=>$data['nombre'], 'telefono'=>$data['telefono'] ?? null, 'placas'=>$data['placas']]);
            }
            $amount = $n <= 26 ? 270 : 240;
            if (empty($data['pago_verificado'])) { $this->error('Confirma que recibiste el pago mensual.'); }
            $method = $data['metodo_pago']; $key = null;
            if (!in_array($method, ['efectivo','yape','plin'], true)) { $this->error('Selecciona un medio de pago válido.'); }
            if ($method === 'efectivo') {
                if (LiquidacionService::centavos($data['efectivo_recibido'] ?? 0) < $amount * 100) { $this->error('El efectivo recibido no cubre la mensualidad.'); }
            } else {
                $ref = strtoupper(trim($data['referencia_pago'] ?? ''));
                if (!preg_match('/\A[A-Z0-9-]{1,80}\z/', $ref)) { $this->error('Indica la operación verificada en la cuenta del negocio.'); }
                $key = $method.':'.$ref;
                if (Pago::where('operacion_unica',$key)->exists()) { $this->error('Esa operación ya fue registrada.'); }
            }
            $abono = Abono::create(['abonado_id'=>$cliente->id, 'espacio_id'=>$space->id, 'inicio'=>$inicio, 'fin'=>$fin,
                'zona'=>$n <= 26 ? 'techo' : 'patio', 'monto'=>$amount, 'registrado_por'=>$staff->id]);
            Pago::create(['abono_id'=>$abono->id, 'codigo_pago'=>'ABO-'.Str::uuid(), 'metodo_pago'=>$method, 'monto'=>$amount,
                'estado'=>'aprobado', 'operacion_unica'=>$key, 'referencia_pago'=>$ref ?? null,
                'pagado_at'=>now(), 'revisado_at'=>now(), 'revisado_por'=>$staff->id, 'motivo_revision'=>'Mensualidad recibida y verificada por el encargado.']);
            return $abono;
        });
    }
    private function error(string $message): never { throw ValidationException::withMessages(['abono'=>$message]); }
}
