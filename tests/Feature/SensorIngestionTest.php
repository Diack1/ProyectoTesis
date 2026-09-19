<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\LecturaSensor;
use App\Models\RegistroOcupacion;
use App\Models\Sensor;
use App\Models\User;
use App\Models\VehiculoTipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SensorIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Sensor $sensor;

    private string $token = 'test-sensor-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $space = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'sensor']);
        $this->sensor = Sensor::create(['codigo_sensor' => 'S1', 'espacio_id' => $space->id, 'tipo_sensor' => 'A02 RS485', 'estado' => 'activo']);
        $this->sensor->forceFill(['integracion_iot' => true, 'token_hash' => hash('sha256', $this->token), 'umbral_ocupado_cm' => 100, 'umbral_libre_cm' => 140, 'distancia_min_cm' => 20, 'distancia_max_cm' => 450, 'lecturas_confirmacion' => 3, 'segundos_sin_senal' => 60])->save();
    }

    private function send(?float $distance, ?string $event = null)
    {
        return $this->withToken($this->token)->postJson(route('iot.lecturas', ['sensor' => 'S1']), ['evento_id' => $event ?? (string) Str::uuid(), 'distancia_cm' => $distance]);
    }

    public function test_auth_is_sensor_scoped_and_duplicate_packets_do_not_confirm_occupancy(): void
    {
        $this->withToken('incorrecto')->postJson(route('iot.lecturas', ['sensor' => 'S1']), ['evento_id' => (string) Str::uuid(), 'distancia_cm' => 50])->assertUnauthorized();
        $id = (string) Str::uuid();
        $this->send(50, $id)->assertOk()->assertJsonPath('resultado', 'confirmando');
        $this->send(50, $id)->assertOk()->assertJsonPath('duplicada', true);
        $this->assertSame(1, $this->sensor->fresh()->cantidad_candidata);
        $this->send(60, $id)->assertConflict();
        $this->assertSame(1, LecturaSensor::count());
    }

    public function test_only_stable_measurements_change_space_and_dead_band_resets_candidate(): void
    {
        $this->send(50)->assertOk();
        $this->send(50)->assertOk();
        $this->assertSame('libre', $this->sensor->espacio->fresh()->getRawOriginal('estado_actual'));
        $this->send(120)->assertJsonPath('resultado', 'zona_intermedia');
        $this->send(50);
        $this->send(50);
        $this->send(50)->assertJsonPath('resultado', 'ocupado');
        $this->assertSame('ocupado', $this->sensor->espacio->fresh()->getRawOriginal('estado_actual'));
        $this->assertSame(1, RegistroOcupacion::count());
        $this->send(50);
        $this->assertSame(1, RegistroOcupacion::count());
    }

    public function test_missing_echo_and_out_of_range_never_free_space(): void
    {
        $this->sensor->espacio->update(['estado_actual' => 'ocupado']);
        $this->send(null)->assertJsonPath('resultado', 'lectura_invalida');
        $this->send(999)->assertJsonPath('resultado', 'lectura_invalida');
        $this->send(0)->assertJsonPath('resultado', 'lectura_invalida');
        $this->assertSame('ocupado', $this->sensor->espacio->fresh()->getRawOriginal('estado_actual'));
    }

    public function test_disconnection_hides_free_space_and_recovery_needs_new_consecutive_samples(): void
    {
        $this->assertSame('sin_senal', $this->sensor->espacio->fresh()->estado_actual);
        $this->send(200);
        $this->send(200);
        $this->send(200);
        $this->assertSame('libre', $this->sensor->espacio->fresh()->estado_actual);
        $this->travel(61)->seconds();
        $this->assertSame('sin_senal', $this->sensor->espacio->fresh()->estado_actual);
        $this->send(200)->assertJsonPath('resultado', 'confirmando');
        $this->assertSame('sin_senal', $this->sensor->espacio->fresh()->estado_actual);
        $this->send(200);
        $this->send(200);
        $this->assertSame('libre', $this->sensor->espacio->fresh()->estado_actual);
    }

    public function test_manual_mode_can_test_sensor_without_changing_occupancy(): void
    {
        $this->sensor->espacio->update(['modo_monitoreo' => 'manual']);
        $this->send(50);
        $this->send(50);
        $this->send(50)->assertJsonPath('estado_estable', 'ocupado');
        $this->assertSame('libre', $this->sensor->espacio->fresh()->getRawOriginal('estado_actual'));
    }

    public function test_staff_can_view_but_only_admin_can_calibrate_and_rotate_keys(): void
    {
        $operator = User::factory()->create(['role' => 'operador']);
        $this->actingAs($operator);
        $this->get(route('admin.sensores.index'))->assertOk()->assertDontSee($this->sensor->token_hash);
        $this->post(route('admin.sensores.token', $this->sensor))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.sensores.token', $this->sensor))->assertSessionHas('sensor_token');
        $this->send(50)->assertUnauthorized();
    }

    public function test_legacy_endpoint_cannot_bypass_calibration_for_enrolled_sensor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operador']), 'sanctum')->postJson('/api/sensores/ocupacion', ['codigo_sensor' => 'S1', 'estado' => 'libre'])->assertConflict();
    }

    public function test_confirmed_occupied_starts_assigned_ticket_once_and_free_does_not_close_it(): void
    {
        $user = User::factory()->create(['role' => 'operador']);
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        $e = Estadia::create(['codigo_ticket' => 'QA-IOT', 'placa' => 'ABC123', 'placa_activa' => 'ABC123', 'espacio_id' => $this->sensor->espacio_id, 'espacio_activo_id' => $this->sensor->espacio_id, 'vehiculo_tipo_id' => $type->id, 'operador_ingreso_id' => $user->id, 'hora_ingreso' => now(), 'fuente_inicio' => 'sensor', 'tarifa_snapshot' => []]);
        $this->send(50);
        $this->send(50);
        $this->assertNull($e->fresh()->inicio_cobro);
        $this->send(50);
        $started = $e->fresh()->inicio_cobro;
        $this->assertNotNull($started);
        $this->travel(5)->seconds();
        $this->send(50);
        $this->assertEquals($started, $e->fresh()->inicio_cobro);
        $this->send(200);
        $this->send(200);
        $this->send(200);
        $this->assertNull($e->fresh()->hora_salida);
        $this->assertSame('ocupado', $this->sensor->espacio->fresh()->estado_actual);
    }

    public function test_calibration_rejects_inverted_thresholds_and_out_of_range_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['umbral_ocupado_cm' => 150, 'umbral_libre_cm' => 100, 'distancia_min_cm' => 20, 'distancia_max_cm' => 450, 'lecturas_confirmacion' => 3, 'segundos_sin_senal' => 60, 'estado' => 'activo'];
        $this->put(route('admin.sensores.update', $this->sensor), $data)->assertSessionHasErrors('umbral_libre_cm');
        $data['umbral_libre_cm'] = 200;
        $this->put(route('admin.sensores.update', $this->sensor), $data)->assertSessionHasNoErrors();
        $this->assertNull($this->sensor->fresh()->estado_estable);
    }

    public function test_one_sensor_credential_cannot_write_another_sensor(): void
    {
        $other = Sensor::create(['codigo_sensor' => 'S2', 'espacio_id' => $this->sensor->espacio_id, 'tipo_sensor' => 'JSN-SR04T', 'estado' => 'activo']);
        $other->forceFill(['integracion_iot' => true, 'token_hash' => hash('sha256', 'different')])->save();
        $this->withToken($this->token)->postJson(route('iot.lecturas',['sensor' => 'S2']),['evento_id' => (string) Str::uuid(), 'distancia_cm' => 100])->assertUnauthorized();
    }
}
