<?php
namespace App\Services;

use App\Models\{Espacio, Estadia, RegistroOcupacion, Reubicacion, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReubicacionService {
    public function mover(Estadia $ticket, array $data, User $staff): void
    {
        DB::transaction(function () use ($ticket, $data, $staff) {
            $sourceId = (int) $data['origen_id']; $targetId = (int) $data['destino_id'];
            if ($sourceId === $targetId) { $this->error('Selecciona otro espacio.'); }
            $spaces = Espacio::whereIn('id', [$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $stay = Estadia::lockForUpdate()->findOrFail($ticket->id);
            $source = $spaces->get($sourceId); $target = $spaces->get($targetId);
            if (!$source || !$target || $stay->espacio_id !== $sourceId || $stay->hora_salida) { $this->error('La ubicación o el ticket cambió. Actualiza antes de mover.'); }
            if ($stay->abono_id) { $this->error('El abonado conserva su espacio asignado. Esta acción es para ocupaciones temporales.'); }
            if (!$stay->inicio_cobro) { $this->error('Confirma primero el inicio de la estancia para conservar su contador.'); }
            if (!$stay->autoriza_movimiento || !$stay->llave_recibida || !$stay->ubicacion_llave) { $this->error('Necesitas la autorización del cliente y la llave registrada.'); }
            if (empty($data['movimiento_confirmado'])) { $this->error('Confirma el movimiento físico antes de cambiar la ubicación.'); }
            if (!$target->activo || $target->estado_actual !== 'libre' || $target->abonoVigente()->exists()) { $this->error('El destino no está libre o pertenece a otro abonado. Elige un espacio libre sin asignación mensual.'); }
            if (!$target->vehiculoTipos()->where('vehiculo_tipos.id',$stay->vehiculo_tipo_id)->where('activo',true)->exists()) { $this->error('El destino no admite este tipo de vehículo.'); }
            if ($target->modo_monitoreo === 'sensor' && (!$target->sensor?->lectura_vigente || !$target->sensor?->integracion_iot)) { $this->error('No hay lectura confiable del sensor de destino.'); }
            $stay->update(['espacio_id'=>$targetId, 'espacio_activo_id'=>$targetId]);
            // The reservation retains its original allocation; the active ticket owns current occupancy.
            if ($source->modo_monitoreo === 'manual') { $source->update(['estado_actual'=>'libre']); }
            if ($target->modo_monitoreo === 'manual') { $target->update(['estado_actual'=>'ocupado']); }
            foreach ([[$sourceId,'libre'],[$targetId,'ocupado']] as [$id,$state]) {
                RegistroOcupacion::create(['espacio_id'=>$id,'estado_detectado'=>$state,'fecha_hora'=>now(),'origen'=>'reubicacion']);
            }
            Reubicacion::create(['estadia_id'=>$stay->id, 'origen_id'=>$sourceId, 'destino_id'=>$targetId, 'operador_id'=>$staff->id, 'motivo'=>$data['motivo']]);
        });
    }
    private function error(string $message): never { throw ValidationException::withMessages(['reubicacion'=>$message]); }
}
