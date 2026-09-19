<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturaSensor extends Model
{
    protected $table = 'lecturas_sensores';

    protected $primaryKey = 'evento_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valida' => 'boolean', 'recibido_at' => 'datetime', 'distancia_cm' => 'decimal:2'];
    }
}
