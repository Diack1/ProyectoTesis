<?php

namespace App\Services;

use App\Models\{ClienteCobro, Tarifa, VentaCorreccion};

class VentaCorreccionPresenter
{
    public function changes(VentaCorreccion $correction): array
    {
        $before = $correction->antes ?? [];
        $after = $correction->despues ?? [];
        $oldSale = $before['venta'] ?? [];
        $newSale = $after['venta'] ?? [];
        $oldData = $oldSale['datos'] ?? [];
        $newData = $newSale['datos'] ?? [];
        $changes = [];

        if ($correction->tipo !== 'modificar_pagos') {
            $this->add($changes, 'Comprobante', $oldSale['tipo_comprobante'] ?? null, $newSale['tipo_comprobante'] ?? null,
                fn ($value) => ucfirst((string) $value));
            $this->add($changes, 'Cliente', $oldSale['cliente'] ?? null, $newSale['cliente'] ?? null,
                fn ($value) => $this->client($value));
            $this->add($changes, 'Documento', $oldSale['documento'] ?? null, $newSale['documento'] ?? null);
            $this->add($changes, 'Tarifa', $oldData['tarifa'] ?? $oldData['tarifa_id'] ?? null,
                $newData['tarifa'] ?? $newData['tarifa_id'] ?? null, fn ($value) => $this->tariff($value));
            $this->add($changes, 'Importe', $oldSale['importe_base'] ?? 0, $newSale['importe_base'] ?? 0, fn ($value) => $this->money($value));
            $this->add($changes, 'Descuento', $oldSale['descuento'] ?? 0, $newSale['descuento'] ?? 0, fn ($value) => $this->money($value));
            $this->add($changes, 'Total a cobrar', $oldSale['total'] ?? 0, $newSale['total'] ?? 0, fn ($value) => $this->money($value));
            $this->add($changes, 'Descuento (%)', $oldData['descuento_porcentaje'] ?? 0, $newData['descuento_porcentaje'] ?? 0,
                fn ($value) => number_format((float) $value, 2).'%');
            $this->add($changes, 'Descuento fijo', $oldData['descuento_monto'] ?? 0, $newData['descuento_monto'] ?? 0,
                fn ($value) => $this->money($value));
            $this->add($changes, 'Motivo del descuento', $oldData['motivo_descuento'] ?? null, $newData['motivo_descuento'] ?? null);
            $this->add($changes, 'Impresión', $oldSale['tipo_impresion'] ?? null, $newSale['tipo_impresion'] ?? null,
                fn ($value) => $value === 'consumo' ? 'Por consumo' : 'Detallado');
            foreach (['placa' => 'Placa', 'vehiculo' => 'Tipo de vehículo', 'espacio' => 'Espacio'] as $key => $label) {
                $this->add($changes, $label, $oldData[$key] ?? null, $newData[$key] ?? null);
            }
        }

        $this->payments($changes, $before['pagos'] ?? [], $after['pagos'] ?? []);
        return $changes;
    }

    private function add(array &$changes, string $label, mixed $before, mixed $after, ?\Closure $format = null): void
    {
        $format ??= fn ($value) => ($value === null || $value === '') ? 'Sin registrar' : (string) $value;
        $old = $format($before);
        $new = $format($after);
        if ($old !== $new) $changes[] = ['label' => $label, 'before' => $old, 'after' => $new];
    }

    private function payments(array &$changes, array $before, array $after): void
    {
        $old = $this->groupPayments($before);
        $new = $this->groupPayments($after);
        if (count($old) === 1 && count($new) === 1) {
            $oldMethod = array_key_first($old);
            $newMethod = array_key_first($new);
            $this->add($changes, 'Método de pago', $oldMethod, $newMethod, fn ($method) => $this->method($method));
            $this->add($changes, 'Pago aplicado', $old[$oldMethod]['amount'], $new[$newMethod]['amount'], fn ($value) => $this->money($value));
        } else {
            foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $method) {
                $oldAmount = $old[$method]['amount'] ?? 0;
                $newAmount = $new[$method]['amount'] ?? 0;
                if (round($oldAmount, 2) !== round($newAmount, 2)) {
                    $changes[] = ['label' => 'Pago en '.$this->method($method),
                        'before' => isset($old[$method]) ? $this->money($oldAmount) : 'Sin pago',
                        'after' => isset($new[$method]) ? $this->money($newAmount) : 'Sin pago'];
                } elseif (($old[$method]['count'] ?? 0) !== ($new[$method]['count'] ?? 0)) {
                    $this->add($changes, 'Pagos en '.$this->method($method), $old[$method]['count'] ?? 0, $new[$method]['count'] ?? 0);
                }
            }
        }
        $this->add($changes, 'Efectivo recibido', $this->sum($old, 'received'), $this->sum($new, 'received'), fn ($value) => $this->money($value));
        $this->add($changes, 'Vuelto', $this->sum($old, 'change'), $this->sum($new, 'change'), fn ($value) => $this->money($value));
    }

    private function groupPayments(array $payments): array
    {
        $grouped = [];
        foreach ($payments as $payment) {
            $method = (string) ($payment['metodo_pago'] ?? 'manual');
            $grouped[$method] ??= ['amount' => 0.0, 'received' => 0.0, 'change' => 0.0, 'count' => 0];
            $amount = (float) ($payment['monto'] ?? 0);
            $grouped[$method]['amount'] += $amount;
            $grouped[$method]['count']++;
            if ($method === 'efectivo') {
                $grouped[$method]['received'] += (float) ($payment['efectivo_recibido'] ?? $amount);
                $grouped[$method]['change'] += (float) ($payment['vuelto'] ?? 0);
            }
        }
        return $grouped;
    }

    private function sum(array $payments, string $key): float
    {
        return array_sum(array_column($payments, $key));
    }

    private function method(?string $method): string
    {
        return ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'yape' => 'Yape',
            'plin' => 'Plin', 'manual' => 'Otros medios'][$method ?? ''] ?? 'Otro medio';
    }

    private function client(mixed $value): string
    {
        if ($value === null || $value === '') return 'Sin cliente';
        if (is_numeric($value)) {
            $client = ClienteCobro::find((int) $value);
            return $client ? trim($client->nombres.' '.$client->apellidos) : 'Cliente no disponible';
        }
        return (string) $value;
    }

    private function tariff(mixed $value): string
    {
        if ($value === null || $value === '') return 'Sin tarifa';
        if (is_numeric($value)) return Tarifa::find((int) $value)?->nombre ?? 'Tarifa no disponible';
        return (string) $value;
    }

    private function money(mixed $value): string
    {
        return 'S/ '.number_format((float) $value, 2);
    }
}
