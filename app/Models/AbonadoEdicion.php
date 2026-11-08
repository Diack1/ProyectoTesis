<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbonadoEdicion extends Model
{
    protected $table = 'abonado_ediciones';
    protected $guarded = ['id'];
    protected function casts(): array { return ['antes' => 'array', 'despues' => 'array']; }
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
    public function abono() { return $this->belongsTo(Abono::class); }
}
