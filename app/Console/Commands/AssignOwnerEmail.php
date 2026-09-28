<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignOwnerEmail extends Command
{
    protected $signature = 'security:assign-owner-email {email} {--apply}';
    protected $description = 'Reasignar el correo al propietario y desactivar al cliente anterior sin borrar su historial';
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return self::FAILURE; }
        return DB::transaction(function () use ($email) {
            $users = User::where('role','super_admin')->orWhere('email',$email)->orderBy('id')->lockForUpdate()->get();
            $owners = $users->where('role','super_admin');
            if ($owners->count() !== 1) { $this->error('Se requiere exactamente un propietario. No se modificó ninguna cuenta.'); return self::FAILURE; }
            $owner = $owners->first();
            $customer = $users->firstWhere('email',$email);
            if ($customer && $customer->id === $owner->id) { $this->info('El propietario ya tiene ese correo.'); return self::SUCCESS; }
            if ($customer && $customer->role !== 'user') { $this->error('El correo pertenece a otra cuenta del personal.'); return self::FAILURE; }
            $this->line('Propietario #'.$owner->id.'; cliente asociado: '.($customer?->id ?? 'ninguno'));
            if ($customer) { $this->line('Se conservarán '.$customer->reservas()->count().' reservas y '.$customer->pagos()->count().' pagos.'); }
            if (!$this->option('apply')) { $this->info('Solo revisión, sin cambios.'); return self::SUCCESS; }
            if ($customer) {
                $customer->forceFill(['email'=>'closed-'.$customer->id.'-'.Str::uuid().'@accounts.invalid','activo'=>false,
                    'password'=>Str::random(64),'remember_token'=>null,'email_verified_at'=>null])->save();
                $customer->revokeAccess();
            }
            $owner->forceFill(['email'=>$email,'email_verified_at'=>null,'remember_token'=>Str::random(60)])->save();
            $owner->revokeAccess();
            $this->info('Correo reasignado; historial conservado y sesiones revocadas. Contraseña del propietario sin cambios.');
            return self::SUCCESS;
        });
    }
}
