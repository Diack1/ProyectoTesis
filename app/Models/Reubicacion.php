<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reubicacion extends Model {
    protected $table = 'reubicaciones';
    protected $guarded = ['id'];
    public function origen() { return $this->belongsTo(Espacio::class, 'origen_id'); }
    public function destino() { return $this->belongsTo(Espacio::class, 'destino_id'); }
    public function operador() { return $this->belongsTo(User::class, 'operador_id'); }
}
