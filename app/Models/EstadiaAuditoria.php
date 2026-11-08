<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadiaAuditoria extends Model
{
    public $timestamps = false;
    protected $table = 'estadia_auditorias';
    protected $fillable = ['estadia_id', 'usuario_id', 'accion', 'valores_anteriores', 'valores_nuevos', 'motivo'];
    protected function casts(): array { return ['valores_anteriores' => 'array', 'valores_nuevos' => 'array', 'created_at' => 'datetime']; }
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
}
