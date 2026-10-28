<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Abono extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['inicio'=>'datetime', 'fin'=>'datetime', 'monto'=>'decimal:2']; }
    public function abonado() { return $this->belongsTo(Abonado::class); }
    public function espacio() { return $this->belongsTo(Espacio::class); }
    public function pago() { return $this->hasOne(Pago::class); }
    public function scopeVigentes($q) { return $q->where('inicio', '<=', now())->where('fin', '>', now())->whereHas('pago', fn ($p) => $p->where('estado', 'aprobado')); }
}
