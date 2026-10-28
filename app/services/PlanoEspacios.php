<?php

namespace App\Services;

class PlanoEspacios
{
    public const TOTAL = 41;

    public static function numero(string $codigo): int
    {
        return preg_match('/^[ET]0*([1-9][0-9]*)$/', $codigo, $m) ? (int) $m[1] : 0;
    }

    public static function posicion(int $n): array
    {
        if ($n <= 10) {
            return [80, 20 + (10 - $n) * 5.8, 17, 5.3];
        }
        if ($n <= 16) {
            return [4 + (16 - $n) * 15.5, 3, 14, 12];
        }
        if ($n <= 23) {
            return [3, 39 + ($n - 17) * 5.8, 17, 5.3];
        }
        // T24–T33 run down beside the office; T34 restarts at the top.
        return [24 + intdiv($n - 24, 10) * 18.5, 20 + (($n - 24) % 10) * 5.8, 17, 5.3];
    }
}
