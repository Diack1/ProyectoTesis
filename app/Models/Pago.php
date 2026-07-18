<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'reserva_id',
        'user_id',
        'codigo_pago',
        'metodo_pago',
        'monto',
        'estado',
        'referencia_pago',
        'comprobante',
        'pagado_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_at' => 'datetime',
        ];
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

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
