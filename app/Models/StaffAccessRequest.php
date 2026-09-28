<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAccessRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['code_hash', 'session_hash', 'credential_hash', 'owner_hash'];
    protected function casts(): array { return ['expires_at'=>'datetime', 'consumed_at'=>'datetime', 'decided_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
