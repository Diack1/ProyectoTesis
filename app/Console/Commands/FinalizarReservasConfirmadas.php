<?php

namespace App\Console\Commands;

use App\Services\ReservaService;
use Illuminate\Console\Command;

class FinalizarReservasConfirmadas extends Command
{
    protected $signature = 'reservas:finalizar';

    protected $description = 'Finaliza las reservas confirmadas cuya hora de salida ya pasó.';

    public function handle(ReservaService $reservaService): int
    {
        $cantidad = $reservaService->finalizarReservasConfirmadas();

        $this->info("Reservas finalizadas: {$cantidad}");

        return self::SUCCESS;
    }
}
