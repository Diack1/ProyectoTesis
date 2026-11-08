<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'datos' => 'array', 'importe_base' => 'decimal:2',
            'descuento' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function pagos() { return $this->hasMany(Pago::class); }
    public function correcciones() { return $this->hasMany(VentaCorreccion::class); }
    public function correccionOrigen() { return $this->hasOne(VentaCorreccion::class, 'venta_nueva_id'); }
    public function anterior() { return $this->belongsTo(self::class, 'venta_anterior_id'); }
}
