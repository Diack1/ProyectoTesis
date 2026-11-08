<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Abono extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['inicio'=>'datetime', 'fin'=>'datetime', 'monto'=>'decimal:2',
        'monto_base'=>'decimal:2', 'descuento_porcentaje'=>'decimal:2', 'descuento_monto'=>'decimal:2',
        'efectivo_recibido'=>'decimal:2', 'vuelto'=>'decimal:2']; }
    public function abonado() { return $this->belongsTo(Abonado::class); }
    public function espacio() { return $this->belongsTo(Espacio::class); }
    public function pago() { return $this->hasOne(Pago::class); }
    public function pagos() { return $this->hasMany(Pago::class); }
    public function estadias() { return $this->hasMany(Estadia::class); }
    public function tarifa() { return $this->belongsTo(Tarifa::class); }
    public function clienteCobro() { return $this->belongsTo(ClienteCobro::class); }
    public function scopeVigentes($q) { return $q->where('inicio', '<=', now())->where('fin', '>', now())->whereHas('pago', fn ($p) => $p->where('estado', 'aprobado')); }
}
