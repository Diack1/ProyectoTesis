<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'reserva_id', 'estadia_id', 'abono_id', 'caja_apertura_id', 'venta_id',
        'user_id',
        'codigo_pago',
        'metodo_pago',
        'monto',
        'estado',
        'referencia_pago',
        'comprobante', 'enviado_at', 'revisado_at', 'revisado_por', 'motivo_revision', 'operacion_unica',
        'pagado_at',
        'efectivo_recibido', 'vuelto',
        'reportable_historico',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2', 'efectivo_recibido' => 'decimal:2', 'vuelto' => 'decimal:2',
            'pagado_at' => 'datetime', 'enviado_at' => 'datetime', 'revisado_at' => 'datetime',
            'reportable_historico' => 'boolean',
        ];
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function venta() { return $this->belongsTo(Venta::class); }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaAprobado(): bool
    {
        return $this->estado === 'aprobado';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
