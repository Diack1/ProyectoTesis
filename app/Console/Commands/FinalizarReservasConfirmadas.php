<?php

namespace App\Console\Commands;

use App\Services\ReservaService;
use Illuminate\Console\Command;

class FinalizarReservasConfirmadas extends Command
{
    protected $signature = 'reservas:finalizar';

    protected $description = 'Libera reservas sin llegada y deja los pagos para revisión manual.';

    public function handle(ReservaService $reservaService): int
    {
        $cantidad = $reservaService->finalizarReservasConfirmadas();

        $this->info("Inasistencias procesadas: {$cantidad}");

        return self::SUCCESS;
    }
}
