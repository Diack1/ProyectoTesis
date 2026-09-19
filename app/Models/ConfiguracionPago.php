<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPago extends Model
{
    protected $table = 'configuracion_pagos';

    protected $guarded = ['id'];

    public static function actual(): self
    {
        return static::first() ?? new static(['minutos_pago' => 10, 'tolerancia_llegada' => 15]);
    }
}
