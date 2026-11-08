<?php

namespace App\Services;

use App\Models\{Abono, Estadia, Impresora, Venta};
use Illuminate\Validation\ValidationException;

class ImpresionService
{
    public function comprobanteAutomatico(Estadia|Abono $origen): array
    {
        $impresora = Impresora::predeterminada();
        if (!$impresora?->directa()) {
            return ['ok'=>false, 'mensaje'=>'No hay una impresora térmica de red o Wi-Fi configurada para impresión automática. Usa Imprimir para hacerlo manualmente.'];
        }
        try {
            if ($origen instanceof Estadia) {
                $origen->load('espacio','vehiculoTipo','pagosSalida');
                $contenido = $this->ticket($origen);
            } else {
                $origen->load('abonado','espacio');
                $contenido = $this->abono($origen);
            }
            $this->enviar($impresora, $contenido);
            return ['ok'=>true, 'mensaje'=>'Comprobante enviado a '.$impresora->nombre.'.'];
        } catch (ValidationException $e) {
            return ['ok'=>false, 'mensaje'=>$e->errors()['impresora'][0] ?? 'No se pudo imprimir. Usa Imprimir para reintentar.'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok'=>false, 'mensaje'=>'No se pudo imprimir automáticamente. Usa Imprimir para reintentar.'];
        }
    }

    public function enviar(Impresora $impresora, string $contenido, ?string $qr = null): void
    {
        if (!$impresora->directa()) throw ValidationException::withMessages(['impresora'=>'Esta impresora se usa mediante el diálogo del navegador.']);
        if (!self::ipPrivada($impresora->direccion_ip)) throw ValidationException::withMessages(['impresora'=>'La impresora debe tener una dirección IPv4 privada de la red local.']);
        $puerto = $impresora->puerto ?: 9100;
        if ($puerto < 1 || $puerto > 65535) throw ValidationException::withMessages(['impresora'=>'Puerto de impresora inválido.']);
        $error = $descripcion = null;
        $conexion = @fsockopen($impresora->direccion_ip, $puerto, $error, $descripcion, 2);
        if (!$conexion) throw ValidationException::withMessages(['impresora'=>'No se pudo conectar con la impresora de red. Comprueba IP, puerto y encendido.']);
        stream_set_timeout($conexion, 3);
        $lineas = explode("\n", trim($contenido));
        $salida = "\x1B\x40\x1B\x61\x01";
        foreach ($lineas as $linea) $salida .= $this->codificar($linea)."\n";
        if ($qr) $salida .= $this->comandoQr($qr);
        $salida .= "\n\n\x1D\x56\x00";
        try {
            $escritos = 0;
            while ($escritos < strlen($salida)) {
                $n = fwrite($conexion, substr($salida, $escritos));
                if ($n === false || $n === 0) throw ValidationException::withMessages(['impresora'=>'La impresora no recibió todos los datos. Revisa su conexión antes de reintentar.']);
                $escritos += $n;
            }
        } finally { fclose($conexion); }
    }

    public function ticket(Estadia $estadia): string
    {
        $negocio = \App\Models\ConfiguracionPago::actual();
        $venta = $estadia->hora_salida ? Venta::where('origen_tipo','estadia')->where('origen_id',$estadia->id)
            ->whereIn('estado',['vigente','corregida'])->latest('version')->first() : null;
        $lineas = [$negocio->razon_social ?: ($negocio->titular ?: 'Parke’o'),
            $estadia->hora_salida ? strtoupper($venta?->tipo_comprobante ?? $estadia->tipo_comprobante ?? 'BOLETA').' · PAGO' : 'TICKET DE ENTRADA',
            $venta?->numero ?? $estadia->codigo_ticket,
            'Placa: '.$estadia->placa,
            'Vehiculo: '.($estadia->vehiculoTipo?->nombre ?: '—'),
            'Espacio: '.($estadia->espacio?->codigo ?: '—'),
            'Entrada: '.$estadia->hora_ingreso->format('d/m/Y H:i'),
            'Tarifa: '.($estadia->tarifa_snapshot['nombre'] ?? 'Registrada')];
        if ($estadia->hora_salida) {
            $lineas[] = 'Salida: '.$estadia->hora_salida->format('d/m/Y H:i');
            $lineas[] = 'Total: S/ '.number_format((float)($venta?->total ?? $estadia->saldo_cobrado ?? 0),2);
            foreach ($estadia->pagosSalida->whereIn('estado',['aprobado','reembolsado']) as $pago)
                $lineas[] = ucfirst($pago->metodo_pago).': S/ '.number_format((float)$pago->monto,2);
            if ((float)$estadia->vuelto > 0) $lineas[] = 'Vuelto: S/ '.number_format((float)$estadia->vuelto,2);
        } else $lineas[] = 'El QR identifica el ticket; no acredita pago.';
        return implode("\n", $lineas);
    }

    public function abono(Abono $abono): string
    {
        return implode("\n", [\App\Models\ConfiguracionPago::actual()->razon_social ?: 'Parke’o', 'COMPROBANTE DE ABONO',
            'ABO-'.str_pad((string)$abono->id,6,'0',STR_PAD_LEFT), 'Cliente: '.$abono->abonado?->nombre,
            'Espacio: '.($abono->espacio_contratado_codigo ?: $abono->espacio?->codigo),
            'Periodo: '.$abono->inicio->format('d/m/Y').' - '.$abono->fin->format('d/m/Y'),
            'Total: S/ '.number_format((float)$abono->monto,2)]);
    }

    public function venta(Venta $venta): string
    {
        $lineas = [\App\Models\ConfiguracionPago::actual()->razon_social ?: 'Parke’o',
            strtoupper($venta->tipo_comprobante).' · '.$venta->numero,
            'Fecha: '.$venta->fecha->format('d/m/Y H:i'), 'Cliente: '.$venta->cliente,
            'Servicio: '.$venta->concepto, 'Total: S/ '.number_format((float)$venta->total,2)];
        if ($venta->estado === 'anulada') $lineas[] = 'ANULADA';
        foreach ($venta->pagos->whereIn('estado',['aprobado','reembolsado']) as $pago)
            $lineas[] = ucfirst($pago->metodo_pago).': S/ '.number_format((float)$pago->monto,2);
        return implode("\n",$lineas);
    }

    public static function ipPrivada(?string $ip): bool
    {
        if (!$ip || !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) return false;
        $n = ip2long($ip);
        return (($n & 0xff000000) === 0x0a000000)
            || (($n & 0xfff00000) === 0xac100000)
            || (($n & 0xffff0000) === 0xc0a80000);
    }

    private function codificar(string $texto): string
    {
        return iconv('UTF-8','CP437//TRANSLIT',$texto) ?: preg_replace('/[^\x20-\x7E]/','', $texto);
    }

    private function comandoQr(string $texto): string
    {
        $bytes = strlen($texto) + 3;
        return "\x1B\x61\x01\x1D\x28\x6B\x04\x00\x31\x41\x32\x00"
            ."\x1D\x28\x6B\x03\x00\x31\x43\x04"
            ."\x1D\x28\x6B".chr($bytes % 256).chr(intdiv($bytes,256))."\x31\x50\x30".$texto
            ."\x1D\x28\x6B\x03\x00\x31\x51\x30\n";
    }
}
