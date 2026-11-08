<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteCobro extends Model
{
    protected $table = 'clientes_cobro';
    protected $fillable = ['nombres', 'apellidos', 'celular', 'tipo_documento', 'numero_documento'];
}
