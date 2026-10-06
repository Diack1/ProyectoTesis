<?php
namespace App\Services;

class DuracionReserva
{
    public const REGLA = 'in:60,120,180,240,300,360,420,480,540,600,660,720';

    public static function minutos(): array
    {
        return range(60, 720, 60);
    }
}
