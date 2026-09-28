<?php
namespace App\Observers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class SecurityObserver {
    private const FIELDS = ['role', 'activo', 'password', 'email', 'pending_email', 'titular', 'telefono',
        'qr_yape', 'qr_plin', 'minutos_pago', 'tolerancia_llegada', 'estado', 'procesado_por', 'revisado_por', 'token_hash', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_hashes'];
    public function created(Model $model): void { $this->record($model, 'created', array_keys($model->getAttributes())); }
    public function updated(Model $model): void { $this->record($model, 'updated', array_keys($model->getChanges())); }
    private function record(Model $model, string $action, array $fields): void {
        $fields = array_values(array_intersect(self::FIELDS, $fields));
        if (!$fields) { return; }
        $attention = $model instanceof \App\Models\ConfiguracionPago
            || (bool) array_intersect($fields, ['role', 'activo', 'password', 'email', 'token_hash', 'two_factor_secret', 'two_factor_recovery_hashes']);
        if ($action === 'created' && $model instanceof \App\Models\User && $model->role === 'user') {
            $attention = false; // Normal customer registration is not a staff security alert.
        }
        \App\Services\SecurityEvents::record(class_basename($model), $model->getKey(), $action, $fields, $attention);
    }
}
