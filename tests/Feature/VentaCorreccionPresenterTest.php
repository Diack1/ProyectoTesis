<?php

namespace Tests\Feature;

use App\Models\{ClienteCobro, Tarifa, VentaCorreccion, VehiculoTipo};
use App\Services\VentaCorreccionPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaCorreccionPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_changed_names_and_amounts_without_internal_fields(): void
    {
        $oldClient = ClienteCobro::create(['nombres' => 'Juan', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678']);
        $newClient = ClienteCobro::create(['nombres' => 'Carlos', 'apellidos' => 'López', 'tipo_documento' => 'DNI', 'numero_documento' => '87654321']);
        $vehicle = VehiculoTipo::create(['codigo' => 'auto', 'nombre' => 'Auto', 'activo' => true]);
        $oldRate = Tarifa::create(['vehiculo_tipo_id' => $vehicle->id, 'nombre' => 'Por hora', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'activo' => true]);
        $newRate = Tarifa::create(['vehiculo_tipo_id' => $vehicle->id, 'nombre' => 'Bloque de 12 horas', 'tipo_tarifa' => 'bloque_horas', 'monto_base' => 40, 'monto_por_hora' => 0, 'tiempo_minimo_minutos' => 720, 'activo' => true]);
        $correction = new VentaCorreccion(['tipo' => 'anular_y_corregir',
            'antes' => ['venta' => ['numero' => 'PK-OLD', 'cliente' => (string) $oldClient->id, 'tipo_comprobante' => 'boleta',
                'importe_base' => '50.00', 'descuento' => '0.00', 'total' => '50.00', 'datos' => ['tarifa' => (string) $oldRate->id]],
                'pagos' => [['metodo_pago' => 'efectivo', 'monto' => '50.00', 'efectivo_recibido' => '50.00', 'caja_apertura_id' => 900]]],
            'despues' => ['venta' => ['numero' => 'PK-NEW', 'cliente' => (string) $newClient->id, 'tipo_comprobante' => 'factura',
                'importe_base' => '40.00', 'descuento' => '0.00', 'total' => '40.00', 'datos' => ['tarifa' => (string) $newRate->id]],
                'pagos' => [['metodo_pago' => 'yape', 'monto' => '40.00']]]]);

        $changes = app(VentaCorreccionPresenter::class)->changes($correction);
        $byLabel = collect($changes)->keyBy('label');
        $this->assertSame('Boleta', $byLabel['Comprobante']['before']);
        $this->assertSame('Factura', $byLabel['Comprobante']['after']);
        $this->assertSame('Juan Pérez', $byLabel['Cliente']['before']);
        $this->assertSame('Carlos López', $byLabel['Cliente']['after']);
        $this->assertSame('Por hora', $byLabel['Tarifa']['before']);
        $this->assertSame('Bloque de 12 horas', $byLabel['Tarifa']['after']);
        $this->assertSame('Efectivo', $byLabel['Método de pago']['before']);
        $this->assertSame('Yape', $byLabel['Método de pago']['after']);
        $this->assertSame('S/ 50.00', $byLabel['Importe']['before']);
        $this->assertSame('S/ 40.00', $byLabel['Importe']['after']);
        $this->assertFalse($byLabel->has('Descuento'));
        $this->assertStringNotContainsString('caja_apertura_id', json_encode($changes));
        $this->assertStringNotContainsString('PK-OLD', json_encode($changes));
    }

    public function test_payment_only_correction_never_claims_sale_fields_changed(): void
    {
        $correction = new VentaCorreccion(['tipo' => 'modificar_pagos',
            'antes' => ['venta' => ['cliente' => 'Juan', 'total' => 100],
                'pagos' => [['metodo_pago' => 'efectivo', 'monto' => 100, 'efectivo_recibido' => 100]]],
            'despues' => ['venta' => ['cliente' => 'Carlos', 'total' => 80],
                'pagos' => [['metodo_pago' => 'efectivo', 'monto' => 40, 'efectivo_recibido' => 40],
                    ['metodo_pago' => 'yape', 'monto' => 60]]]]);
        $changes = collect(app(VentaCorreccionPresenter::class)->changes($correction))->keyBy('label');
        $this->assertSame('S/ 100.00', $changes['Pago en Efectivo']['before']);
        $this->assertSame('S/ 40.00', $changes['Pago en Efectivo']['after']);
        $this->assertSame('Sin pago', $changes['Pago en Yape']['before']);
        $this->assertSame('S/ 60.00', $changes['Pago en Yape']['after']);
        $this->assertFalse($changes->has('Cliente'));
        $this->assertFalse($changes->has('Total a cobrar'));
    }
}
