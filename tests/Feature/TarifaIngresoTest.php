<?php

namespace Tests\Feature;

use App\Models\{Espacio, Estadia, Tarifa, User, VehiculoTipo};
use App\Services\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarifaIngresoTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_lists_configured_rates_and_preserves_selected_hourly_conditions(): void
    {
        $staff = User::factory()->create(['role' => 'operador']);
        $auto = VehiculoTipo::create(['codigo' => 'auto', 'nombre' => 'Auto', 'activo' => true]);
        $moto = VehiculoTipo::create(['codigo' => 'moto', 'nombre' => 'Moto', 'activo' => true]);
        $space = Espacio::create(['codigo' => 'T01', 'activo' => true, 'estado_actual' => 'libre', 'modo_monitoreo' => 'manual']);
        $space->vehiculoTipos()->attach([$auto->id, $moto->id]);
        $hour = Tarifa::create(['vehiculo_tipo_id' => $auto->id, 'nombre' => 'Auto · hora',
            'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'monto_por_fraccion' => 1,
            'minutos_fraccion' => 15, 'tiempo_minimo_minutos' => 60, 'tolerancia_minutos' => 10,
            'cobro_intervalos' => true, 'activo' => true]);
        $block = Tarifa::create(['vehiculo_tipo_id' => $auto->id, 'nombre' => 'Auto · 2 horas',
            'tipo_tarifa' => 'bloque_horas', 'monto_base' => 14, 'monto_por_hora' => 0, 'tiempo_minimo_minutos' => 120,
            'cobro_intervalos' => true, 'activo' => true]);
        $monthly = Tarifa::create(['vehiculo_tipo_id' => $auto->id, 'nombre' => 'Auto · mensual',
            'tipo_tarifa' => 'mensual', 'monto_base' => 100, 'monto_por_hora' => 0, 'activo' => true]);
        $motoHour = Tarifa::create(['vehiculo_tipo_id' => $moto->id, 'nombre' => 'Moto · hora',
            'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 3, 'activo' => true]);

        $this->actingAs($staff)->get(route('admin.estadias.create'))->assertOk()
            ->assertSee('value="'.$hour->id.'" data-type="'.$auto->id.'"', false)
            ->assertSee('value="'.$block->id.'" data-type="'.$auto->id.'"', false)
            ->assertSee('value="'.$motoHour->id.'" data-type="'.$moto->id.'"', false)
            ->assertDontSee('value="'.$monthly->id.'" data-type=', false);
        $this->get(route('admin.espacios.operar', $space))->assertOk()->assertSee('Bloque de 2 horas');
        $base = ['placa' => 'ABC123', 'espacio_id' => $space->id, 'vehiculo_tipo_id' => $auto->id];
        $this->post(route('admin.estadias.store'), $base)->assertSessionHasErrors('tarifa_id');
        $this->post(route('admin.estadias.store'), $base + ['tarifa_id' => $monthly->id])->assertSessionHasErrors('tarifa_id');
        $this->post(route('admin.estadias.store'), $base + ['tarifa_id' => $motoHour->id])->assertSessionHasErrors('tarifa_id');
        $this->assertSame(0, Estadia::count());

        $this->post(route('admin.estadias.store'), $base + ['tarifa_id' => $hour->id])->assertSessionHasNoErrors();
        $stay = Estadia::sole();
        $this->assertSame($hour->id, $stay->tarifa_id);
        $this->assertSame('por_hora', $stay->tarifa_snapshot['tipo_tarifa']);
        $this->assertArrayNotHasKey('bloque_minutos', $stay->tarifa_snapshot);
        $this->assertSame(500, app(LiquidacionService::class)->calcular($stay, $stay->inicio_cobro->copy()->addMinutes(70))['saldo_centavos']);
        $this->assertSame(600, app(LiquidacionService::class)->calcular($stay, $stay->inicio_cobro->copy()->addMinutes(71))['saldo_centavos']);
        $this->assertSame(900, app(LiquidacionService::class)->calcular($stay, $stay->inicio_cobro->copy()->addMinutes(120))['saldo_centavos']);

        $hour->update(['monto_por_hora' => 8, 'monto_por_fraccion' => 2, 'tolerancia_minutos' => 5]);
        $block->update(['monto_base' => 16]);
        $this->assertSame(600, app(LiquidacionService::class)->calcular($stay->fresh(), $stay->inicio_cobro->copy()->addMinutes(71))['saldo_centavos']);
        $this->get(route('admin.estadias.create'))->assertOk()->assertSee('S/ 8.00');

        $other = Espacio::create(['codigo' => 'T02', 'activo' => true, 'estado_actual' => 'libre', 'modo_monitoreo' => 'manual']);
        $other->vehiculoTipos()->attach($auto);
        $this->post(route('admin.estadias.store'), ['placa' => 'DEF456', 'espacio_id' => $other->id,
            'vehiculo_tipo_id' => $auto->id, 'tarifa_id' => $block->id])->assertSessionHasNoErrors();
        $blockStay = Estadia::where('placa', 'DEF456')->sole();
        $this->assertSame($block->id, $blockStay->tarifa_id);
        $this->assertSame(1600, app(LiquidacionService::class)->calcular($blockStay, $blockStay->inicio_cobro->copy()->addMinutes(125))['saldo_centavos']);
        $this->assertSame(1800, app(LiquidacionService::class)->calcular($blockStay, $blockStay->inicio_cobro->copy()->addMinutes(126))['saldo_centavos']);
        $hour->update(['monto_por_fraccion' => 9]);
        $this->assertSame(1800, app(LiquidacionService::class)->calcular($blockStay->fresh(), $blockStay->inicio_cobro->copy()->addMinutes(126))['saldo_centavos']);
    }
}
