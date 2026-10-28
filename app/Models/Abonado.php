<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Abonado extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['placas' => 'array']; }
    public function abonos() { return $this->hasMany(Abono::class)->orderByDesc('inicio'); }
}
