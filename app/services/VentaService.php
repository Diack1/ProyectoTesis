<?php

namespace App\Services;

use App\Models\{Abono, CajaApertura, CajaMovimiento, ClienteCobro, Estadia, Pago, Reembolso, Reserva, User, Venta, VentaCorreccion};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function sincronizar(): void
    {
        foreach (['estadia', 'abono', 'reserva'] as $tipo) {
            $campo = $tipo.'_id';
            Pago::whereNull('venta_id')->whereNotNull($campo)->whereIn('estado', ['aprobado', 'reembolsado'])
                ->select($campo)->distinct()->orderBy($campo)->pluck($campo)->each(fn ($id) => $this->desdeOrigen($tipo, (int) $id));
        }
        // Un cobro con descuento del 100 % no produce pagos, pero sí un comprobante.
        Estadia::whereNotNull('hora_salida')->where('saldo_cobrado', 0)
            ->whereNotIn('id', Venta::where('origen_tipo', 'estadia')->select('origen_id'))
            ->select('id')->orderBy('id')->pluck('id')->each(fn ($id) => $this->desdeOrigen('estadia', (int) $id));
    }

    public function desdeOrigen(string $tipo, int $id): ?Venta
    {
        return DB::transaction(function () use ($tipo, $id) {
            $existente = Venta::where('origen_tipo', $tipo)->where('origen_id', $id)->where('version', 1)->first();
            if ($existente) return $existente;
            $origen = match ($tipo) {
                'estadia' => Estadia::with(['clienteCobro', 'espacio', 'vehiculoTipo', 'operadorSalida'])->find($id),
                'abono' => Abono::with(['clienteCobro', 'abonado', 'espacio', 'tarifa'])->find($id),
                'reserva' => Reserva::with(['usuario', 'espacio'])->find($id),
            };
            if (!$origen || ($tipo === 'estadia' && !$origen->hora_salida)) return null;
            $pagos = Pago::where($tipo.'_id', $id)->whereNull('venta_id')
                ->whereIn('estado', ['aprobado', 'reembolsado'])->lockForUpdate()->get();
            if ($pagos->isEmpty() && !($tipo === 'estadia' && (float) $origen->saldo_cobrado === 0.0)) return null;
            $cliente = $origen->clienteCobro ?? null;
            $nombre = $cliente ? trim($cliente->nombres.' '.$cliente->apellidos) : ($tipo === 'abono' ? $origen->abonado?->nombre : ($tipo === 'reserva' ? $origen->usuario?->name : null));
            $total = $pagos->sum('monto');
            $base = $tipo === 'estadia' ? (float) $total + (float) $origen->descuento_monto : ($tipo === 'abono' ? ($origen->monto_base ?? $total) : $total);
            $descuento = $tipo === 'reserva' ? 0 : max(0, (float) $base - (float) $total);
            $datos = match ($tipo) {
                'estadia' => ['placa'=>$origen->placa, 'vehiculo'=>$origen->vehiculoTipo?->nombre,
                    'espacio'=>$origen->espacio?->codigo, 'entrada'=>$origen->hora_ingreso?->format('d/m/Y H:i'),
                    'salida'=>$origen->hora_salida?->format('d/m/Y H:i'), 'tiempo'=>$origen->minutos_cobrados.' min',
                    'tarifa'=>$origen->tarifa_snapshot['nombre'] ?? 'Tarifa registrada', 'cajero'=>$origen->operadorSalida?->name,
                    'motivo_descuento'=>$origen->motivo_descuento],
                'abono' => ['placa'=>implode(', ', $origen->abonado?->placas ?? []), 'espacio'=>$origen->espacio_contratado_codigo ?: $origen->espacio?->codigo,
                    'entrada'=>$origen->inicio?->format('d/m/Y H:i'), 'salida'=>$origen->fin?->format('d/m/Y H:i'),
                    'tiempo'=>$origen->meses.' mes(es)', 'tarifa'=>$origen->tarifa_contratada_nombre ?: $origen->tarifa?->nombre,
                    'cajero'=>User::find($origen->registrado_por)?->name, 'motivo_descuento'=>$origen->motivo_descuento],
                'reserva' => ['placa'=>$origen->placa, 'espacio'=>$origen->espacio?->codigo,
                    'entrada'=>null, 'salida'=>null, 'tiempo'=>($origen->duracion_minutos ?? 0).' min',
                    'tarifa'=>$origen->tarifa_nombre ?? 'Reserva', 'cajero'=>null],
            };
            $numero = match ($tipo) {
                'estadia' => $origen->codigo_ticket ?: 'EST-'.$id,
                'abono' => 'ABO-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'reserva' => $origen->codigo_corto,
            };
            $venta = Venta::create(['origen_tipo'=>$tipo, 'origen_id'=>$id, 'version'=>1, 'numero'=>$numero,
                'caja_apertura_id'=>$pagos->first(fn ($p) => $p->caja_apertura_id)?->caja_apertura_id ?? CajaApertura::where('abierta_unica', 1)->value('id'),
                'fecha'=>$pagos->min('pagado_at') ?: ($origen->hora_salida ?? $origen->inicio ?? now()),
                'cliente'=>$nombre ?: 'Cliente general', 'documento'=>$cliente ? $cliente->tipo_documento.' '.$cliente->numero_documento : null,
                'tipo_comprobante'=>$origen->tipo_comprobante ?? 'boleta', 'tipo_impresion'=>$origen->tipo_impresion ?? 'detallado',
                'concepto'=>$tipo === 'abono' ? 'Abono mensual · '.$datos['placa'] : ($datos['placa'] ?: 'Reserva'),
                'importe_base'=>$base, 'descuento'=>$descuento, 'total'=>$total,
                'datos'=>$datos]);
            Pago::whereIn('id', $pagos->pluck('id'))->update(['venta_id'=>$venta->id]);
            return $venta;
        });
    }

    public function corregir(Venta $venta, array $data, User $usuario, bool $soloPagos): Venta
    {
        return DB::transaction(function () use ($venta, $data, $usuario, $soloPagos) {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);
            if (VentaCorreccion::where('idempotencia', $data['idempotencia'])->exists()) {
                throw ValidationException::withMessages(['idempotencia'=>'Esta corrección ya se registró. Actualiza el historial.']);
            }
            if (!in_array($venta->estado, ['vigente','corregida'], true)) throw ValidationException::withMessages(['venta'=>'Este comprobante fue anulado. Actualiza el historial.']);
            if ($venta->sunat_estado === 'emitido') throw ValidationException::withMessages(['venta'=>'Este comprobante electrónico requiere primero la nota o procedimiento SUNAT correspondiente.']);
            $caja = app(CajaService::class)->abierta(true);
            $anteriores = $venta->pagos()->whereIn('estado', ['aprobado', 'reembolsado'])->lockForUpdate()->get();
            if (Reembolso::whereIn('pago_id', $anteriores->pluck('id'))->where('estado', 'aprobado')->exists()) {
                throw ValidationException::withMessages(['venta'=>'Esta venta tiene un reembolso. Revisa la devolución antes de corregirla.']);
            }
            $total = $soloPagos ? LiquidacionService::centavos($venta->total) : LiquidacionService::centavos($data['total']);
            $base = $soloPagos ? LiquidacionService::centavos($venta->importe_base) : LiquidacionService::centavos($data['importe_base']);
            if ($total < 0 || $base < $total || $base > 99999999) throw ValidationException::withMessages(['total'=>'El total debe estar entre cero y el importe base.']);
            if (!$soloPagos && app(CobroPresencialService::class)->importe($base, $data)['total'] !== $total) {
                throw ValidationException::withMessages(['total'=>'El total no coincide con el descuento indicado. Actualiza la vista previa.']);
            }
            $pagos = $this->validarPagos($data['pagos'] ?? [], $total);
            $antes = ['venta'=>$venta->only(['numero','cliente','documento','tipo_comprobante','tipo_impresion','concepto','importe_base','descuento','total','datos','estado']),
                'pagos'=>$anteriores->map(fn ($p) => $p->only(['metodo_pago','monto','efectivo_recibido','vuelto','referencia_pago','caja_apertura_id','caja_cierre_id']))->all()];
            $nueva = $venta;
            if (!$soloPagos) {
                $cliente = !empty($data['cliente_cobro_id']) ? ClienteCobro::findOrFail($data['cliente_cobro_id']) : null;
                $version = Venta::where('origen_tipo', $venta->origen_tipo)->where('origen_id', $venta->origen_id)->max('version') + 1;
                $detalles = $venta->datos ?? [];
                $detalles['tarifa'] = $data['tarifa'] ?? ($detalles['tarifa'] ?? null);
                $detalles['motivo_descuento'] = $data['motivo_descuento'] ?? null;
                $detalles['descuento_porcentaje'] = $data['descuento_porcentaje'];
                $detalles['descuento_monto'] = $data['descuento_monto'];
                $nueva = Venta::create(['origen_tipo'=>$venta->origen_tipo, 'origen_id'=>$venta->origen_id, 'version'=>$version,
                    'caja_apertura_id'=>$caja->id,
                    'numero'=>$venta->numero.'-C'.$version, 'fecha'=>now(), 'cliente'=>$cliente ? trim($cliente->nombres.' '.$cliente->apellidos) : $venta->cliente,
                    'documento'=>$cliente ? $cliente->tipo_documento.' '.$cliente->numero_documento : $venta->documento,
                    'tipo_comprobante'=>$data['tipo_comprobante'], 'tipo_impresion'=>$data['tipo_impresion'],
                    'concepto'=>$venta->concepto, 'importe_base'=>$base/100, 'descuento'=>($base-$total)/100,
                    'total'=>$total/100, 'datos'=>$detalles, 'venta_anterior_id'=>$venta->id]);
                $venta->update(['estado'=>'anulada']);
                $this->actualizarOrigen($nueva, $cliente);
            }
            $despues = ['venta'=>$nueva->only(['numero','cliente','documento','tipo_comprobante','tipo_impresion','concepto','importe_base','descuento','total','datos','estado']),
                'pagos'=>array_map(fn ($p) => ['metodo_pago'=>$p['metodo_pago'], 'monto'=>$p['monto']/100,
                    'efectivo_recibido'=>$p['efectivo_recibido']/100, 'vuelto'=>$p['vuelto']/100], $pagos)];
            $correccion = VentaCorreccion::create(['venta_id'=>$venta->id, 'venta_nueva_id'=>$soloPagos ? null : $nueva->id,
                'usuario_id'=>$usuario->id, 'tipo'=>$soloPagos ? 'modificar_pagos' : 'anular_y_corregir',
                'motivo'=>$data['motivo'], 'antes'=>$antes, 'despues'=>$despues, 'idempotencia'=>$data['idempotencia']]);
            if ($soloPagos) $venta->update(['estado'=>'corregida']);
            foreach ($anteriores as $pago) {
                if ($pago->caja_apertura_id !== $caja->id && (float) $pago->monto > 0) {
                    CajaMovimiento::create(['caja_apertura_id'=>$caja->id, 'operador_id'=>$usuario->id,
                        'tipo'=>'egreso', 'metodo_pago'=>$pago->metodo_pago, 'monto'=>$pago->monto,
                        'motivo'=>'Reversión '.$venta->numero.' · '.$data['motivo'],
                        'ajuste_venta'=>true, 'venta_correccion_id'=>$correccion->id]);
                }
                $pago->update(['estado'=>'cancelado', 'reportable_historico'=>$pago->caja_apertura_id !== $caja->id,
                    'motivo_revision'=>'Corregido por '.$usuario->name.': '.$data['motivo']]);
            }
            foreach ($pagos as $pago) {
                Pago::create(['venta_id'=>$nueva->id, $venta->origen_tipo.'_id'=>$venta->origen_id,
                    'caja_apertura_id'=>$caja->id, 'codigo_pago'=>'CAJA-'.Str::uuid(),
                    'metodo_pago'=>$pago['metodo_pago'], 'monto'=>$pago['monto']/100,
                    'efectivo_recibido'=>$pago['efectivo_recibido']/100, 'vuelto'=>$pago['vuelto']/100,
                    'estado'=>'aprobado', 'pagado_at'=>now(), 'revisado_at'=>now(), 'revisado_por'=>$usuario->id,
                    'motivo_revision'=>'Corrección '.$correccion->id.': '.$data['motivo']]);
            }
            $efectivoRecibido = array_sum(array_column(array_filter($pagos, fn ($p) => $p['metodo_pago'] === 'efectivo'), 'efectivo_recibido'));
            $vuelto = array_sum(array_column($pagos, 'vuelto'));
            $origen = $venta->origen_tipo === 'estadia' ? Estadia::find($venta->origen_id) : ($venta->origen_tipo === 'abono' ? Abono::find($venta->origen_id) : null);
            $origen?->update(['efectivo_recibido'=>$efectivoRecibido / 100, 'vuelto'=>$vuelto / 100]);
            return $nueva;
        });
    }

    private function validarPagos(array $pagos, int $total): array
    {
        $resultado = []; $sum = 0;
        foreach ($pagos as $pago) {
            $medio = $pago['metodo_pago'] ?? null;
            if (!in_array($medio, ['efectivo','tarjeta','yape','plin','manual'], true)) throw ValidationException::withMessages(['pagos'=>'Método de pago no válido.']);
            $monto = LiquidacionService::centavos($pago['monto'] ?? 0);
            $recibido = $medio === 'efectivo' ? LiquidacionService::centavos($pago['efectivo_recibido'] ?? 0) : 0;
            if ($monto <= 0 || ($medio === 'efectivo' && $recibido < $monto)) throw ValidationException::withMessages(['pagos'=>'Revisa el monto aplicado y el efectivo recibido.']);
            $sum += $monto;
            $resultado[] = ['metodo_pago'=>$medio, 'monto'=>$monto, 'efectivo_recibido'=>$recibido, 'vuelto'=>$recibido-$monto > 0 ? $recibido-$monto : 0];
        }
        if ($sum !== $total) throw ValidationException::withMessages(['pagos'=>'Los pagos aplicados deben cubrir exactamente el total de la venta.']);
        return $resultado;
    }

    private function actualizarOrigen(Venta $venta, ?ClienteCobro $cliente): void
    {
        if ($venta->origen_tipo === 'estadia') {
            $estadia = Estadia::findOrFail($venta->origen_id);
            $estadia->update(['monto_total'=>((float) $venta->total + (float) $estadia->monto_adelantado), 'saldo_cobrado'=>$venta->total,
                'descuento_monto'=>$venta->datos['descuento_monto'] ?? $venta->descuento,
                'descuento_porcentaje'=>$venta->datos['descuento_porcentaje'] ?? 0,
                'motivo_descuento'=>$venta->datos['motivo_descuento'] ?? null,
                'tipo_comprobante'=>$venta->tipo_comprobante, 'tipo_impresion'=>$venta->tipo_impresion,
                'cliente_cobro_id'=>$cliente?->id ?? Estadia::find($venta->origen_id)?->cliente_cobro_id]);
        } elseif ($venta->origen_tipo === 'abono') {
            Abono::whereKey($venta->origen_id)->update(['monto'=>$venta->total, 'monto_base'=>$venta->importe_base,
                'descuento_monto'=>$venta->datos['descuento_monto'] ?? $venta->descuento,
                'descuento_porcentaje'=>$venta->datos['descuento_porcentaje'] ?? 0,
                'motivo_descuento'=>$venta->datos['motivo_descuento'] ?? null,
                'tipo_comprobante'=>$venta->tipo_comprobante, 'tipo_impresion'=>$venta->tipo_impresion,
                'cliente_cobro_id'=>$cliente?->id ?? Abono::find($venta->origen_id)?->cliente_cobro_id]);
        }
    }
}
