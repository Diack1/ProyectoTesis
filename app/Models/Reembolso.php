<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reembolso extends Model
{
    protected $table = 'reembolsos';

    protected $fillable = [
        'reserva_id',
        'pago_id',
        'user_id',
        'monto',
        'motivo',
        'estado',
        'solicitado_at',
        'procesado_at',
        'procesado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'solicitado_at' => 'datetime',
            'procesado_at' => 'datetime',
        ];
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function procesadoPor()
    {
        return $this->belongsTo(User::class, 'procesado_por');
    }
}
