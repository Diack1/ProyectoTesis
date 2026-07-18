<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReservaService;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'reservas:expirar';

    protected $description = 'Expira las reservas pendientes de pago cuyo tiempo límite ya venció.';

    public function handle(ReservaService $reservaService): int
    {
        $cantidad = $reservaService->expirarReservasPendientes();

        $this->info("Reservas expiradas: {$cantidad}");

        return self::SUCCESS;
    }
}
