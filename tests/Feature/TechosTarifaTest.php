<?php

namespace Tests\Feature;

use App\Models\{Espacio, Estadia, Tarifa, VehiculoTipo};
use App\Services\{LiquidacionService, PlanoEspacios, TarifaService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechosTarifaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_spaces_and_applies_hour_or_fraction_to_both_vehicles(): void
    {
        $auto = VehiculoTipo::create(['codigo' => 'auto', 'nombre' => 'Auto', 'activo' => true]);
        $moto = VehiculoTipo::create(['codigo' => 'moto', 'nombre' => 'Moto', 'activo' => true]);
        $space = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'ocupado', 'activo' => false]);
        $space->vehiculoTipos()->attach($auto);
        $motorate = Tarifa::create(['vehiculo_tipo_id' => $moto->id, 'nombre' => 'Moto', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 3, 'activo' => true]);
        $migration = require database_path('migrations/2026_10_26_000001_configurar_techos_y_tarifas.php');
        $migration->up();
        $this->assertDatabaseHas('espacios', ['id' => $space->id, 'codigo' => 'T1', 'estado_actual' => 'ocupado', 'activo' => false]);
        $this->assertSame(38, Espacio::count());
        $this->assertTrue($motorate->fresh()->activo);
        foreach (VehiculoTipo::whereIn('codigo', ['auto', 'camioneta'])->get() as $type) {
            foreach ([1 => 5, 44 => 5, 45 => 5, 60 => 5, 61 => 10, 120 => 10, 121 => 15] as $minutes => $price) {
                $quote = app(TarifaService::class)->calcularMonto($type, now()->setTime(23, 0), $minutes);
                $this->assertEquals($price, $quote['monto_total']);
                $stay = new Estadia(['inicio_cobro' => now()->subMinutes($minutes), 'tarifa_snapshot' => $quote['tarifa']->toArray(), 'monto_adelantado' => 0]);
                $this->assertSame($price * 100, app(LiquidacionService::class)->calcular($stay, $stay->inicio_cobro->copy()->addMinutes($minutes))['total_centavos']);
            }
            $this->assertTrue($space->vehiculoTipos()->where('vehiculo_tipos.id', $type->id)->exists());
        }
    }

    public function test_final_order_preserves_left_and_office_spaces(): void
    {
        $left = Espacio::create(['codigo' => 'T21', 'estado_actual' => 'ocupado', 'activo' => true]);
        $office = Espacio::create(['codigo' => 'T28', 'estado_actual' => 'libre', 'activo' => false]);
        $right = Espacio::create(['codigo' => 'T19', 'estado_actual' => 'libre', 'activo' => true]);
        $migration = require database_path('migrations/2026_10_26_000003_ordenar_plano_41_techos.php');
        $migration->up();
        $this->assertSame(41, Espacio::count());
        $this->assertDatabaseHas('espacios', ['id' => $left->id, 'codigo' => 'T17', 'estado_actual' => 'ocupado']);
        $this->assertDatabaseHas('espacios', ['id' => $office->id, 'codigo' => 'T24', 'activo' => false]);
        $this->assertDatabaseHas('espacios', ['id' => $right->id, 'codigo' => 'T1']);
    }

    public function test_layout_has_41_distinct_positions_with_no_overlap(): void
    {
        $positions = [];
        foreach (range(1, 41) as $n) {
            $p = PlanoEspacios::posicion($n);
            $this->assertSame($n, PlanoEspacios::numero('T'.$n));
            foreach ($positions as $other) {
                $this->assertFalse($p[0] < $other[0]+$other[2] && $p[0]+$p[2] > $other[0] && $p[1] < $other[1]+$other[3] && $p[1]+$p[3] > $other[1]);
            }
            $positions[] = $p;
        }
        $this->assertCount(6, array_filter($positions, fn ($p) => $p[1] === 3));
        $this->assertCount(10, array_filter($positions, fn ($p) => $p[0] === 80));
        $this->assertCount(7, array_filter($positions, fn ($p) => $p[0] === 3));
        foreach (range(27, 41) as $n) {
            $this->assertSame(array_slice(PlanoEspacios::posicion(24), 2), array_slice(PlanoEspacios::posicion($n), 2));
        }
        $this->assertGreaterThan(PlanoEspacios::posicion(10)[1], PlanoEspacios::posicion(1)[1]);
        $this->assertGreaterThan(PlanoEspacios::posicion(16)[0], PlanoEspacios::posicion(11)[0]);
        foreach ([24 => 33, 34 => 41] as $first => $last) {
            $this->assertEquals(20, PlanoEspacios::posicion($first)[1]);
            foreach (range($first + 1, $last) as $n) {
                $this->assertSame(PlanoEspacios::posicion($first)[0], PlanoEspacios::posicion($n)[0]);
                $this->assertGreaterThan(PlanoEspacios::posicion($n - 1)[1], PlanoEspacios::posicion($n)[1]);
            }
        }
    }

    public function test_added_spaces_use_manual_control_without_changing_occupancy(): void
    {
        $office = Espacio::create(['codigo' => 'T24', 'modo_monitoreo' => 'sensor', 'activo' => true]);
        foreach (range(27, 41) as $n) {
            Espacio::create(['codigo' => 'T'.$n, 'modo_monitoreo' => 'sensor', 'incluido_estudio' => true, 'estado_actual' => 'ocupado', 'activo' => true]);
        }
        $migration = require database_path('migrations/2026_10_26_000004_control_manual_espacios_centrales.php');
        $migration->up();
        $this->assertSame('sensor', $office->fresh()->modo_monitoreo);
        $this->assertSame(15, Espacio::where('modo_monitoreo', 'manual')->where('incluido_estudio', false)->where('estado_actual', 'ocupado')->where('activo', true)->count());
    }

    public function test_missing_central_spaces_become_operational_without_sensors_and_codes_are_padded(): void
    {
        $type = VehiculoTipo::create(['codigo' => 'auto', 'nombre' => 'Auto', 'activo' => true]);
        Tarifa::create(['vehiculo_tipo_id' => $type->id, 'nombre' => 'Hora', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'activo' => true]);
        $original = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'libre', 'activo' => true]);
        $occupied = Espacio::create(['codigo' => 'E30', 'estado_actual' => 'ocupado', 'activo' => false, 'modo_monitoreo' => 'sensor']);
        $migration = require database_path('migrations/2026_10_26_000005_habilitar_techos_manuales.php');
        $migration->up();
        $migration->up();
        $this->assertSame('T01', $original->fresh()->codigo);
        $this->assertSame(16, Espacio::count());
        $this->assertDatabaseHas('espacios', ['id' => $occupied->id, 'codigo' => 'T30', 'estado_actual' => 'ocupado', 'activo' => true, 'modo_monitoreo' => 'manual']);
        $availability = app(\App\Services\ReservaDisponibilidadService::class);
        foreach (range(27, 41) as $n) {
            $space = Espacio::where('codigo', 'T'.$n)->firstOrFail();
            $this->assertSame($n !== 30, $availability->estadoParaTarjeta($space)['puede_reservar']);
        }
        $space = Espacio::where('codigo', 'T41')->firstOrFail();
        $this->actingAs(\App\Models\User::factory()->create())->get(route('reservas.create', $space))->assertOk();
        $operator = \App\Models\User::factory()->create(['role' => 'operador']);
        $stay = app(\App\Services\EstadiaService::class)->ingresar(['placa' => 'ABC123', 'espacio_id' => $space->id, 'vehiculo_tipo_id' => $type->id], $operator);
        $this->assertNotNull($stay->inicio_cobro);
        $this->assertSame('manual', $stay->fuente_inicio);
        $this->assertSame('ocupado', $space->fresh()->estado_actual);
        $this->assertDatabaseCount('sensores', 0);
    }
}
