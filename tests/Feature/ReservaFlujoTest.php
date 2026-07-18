<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Sensor;
use App\Models\Tarifa;
use App\Models\User;
use App\Models\VehiculoTipo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservaFlujoTest extends TestCase
{
    use RefreshDatabase;

    private VehiculoTipo $vehiculoTipo;
    private VehiculoTipo $motoTipo;
    private Espacio $espacio;
    private Sensor $sensor;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00', 'America/Lima'));

        config([
            'services.sensores.url' => 'http://200.234.236.154/api/sensores',
            'services.sensores.token' => 'test-token',
        ]);

        $this->user = User::factory()->create([
            'role' => 'user',
            'activo' => true,
        ]);

        $this->vehiculoTipo = VehiculoTipo::create([
            'nombre' => 'Auto',
            'codigo' => 'auto',
            'activo' => true,
        ]);

        $this->motoTipo = VehiculoTipo::create([
            'nombre' => 'Moto',
            'codigo' => 'moto',
            'activo' => true,
        ]);

        $this->espacio = Espacio::create([
            'codigo' => 'E01',
            'descripcion' => 'Espacio E01',
            'estado_actual' => 'libre',
            'activo' => true,
        ]);

        $this->espacio->vehiculoTipos()->attach([
            $this->vehiculoTipo->id,
            $this->motoTipo->id,
        ]);

        $this->sensor = Sensor::create([
            'espacio_id' => $this->espacio->id,
            'codigo_sensor' => 'SENSOR_01',
            'tipo_sensor' => 'simulado',
            'estado' => 'activo',
        ]);

        Tarifa::create([
            'vehiculo_tipo_id' => $this->vehiculoTipo->id,
            'nombre' => 'Tarifa auto por hora',
            'tipo_tarifa' => 'por_hora',
            'monto_base' => 0,
            'monto_por_hora' => 5,
            'monto_por_fraccion' => 2.5,
            'minutos_fraccion' => 30,
            'tiempo_minimo_minutos' => 60,
            'tolerancia_minutos' => 10,
            'penalidad_por_fraccion' => 3,
            'activo' => true,
            'prioridad' => 1,
        ]);

        Tarifa::create([
            'vehiculo_tipo_id' => $this->motoTipo->id,
            'nombre' => 'Tarifa moto por hora',
            'tipo_tarifa' => 'por_hora',
            'monto_base' => 0,
            'monto_por_hora' => 3,
            'monto_por_fraccion' => 1.5,
            'minutos_fraccion' => 30,
            'tiempo_minimo_minutos' => 60,
            'tolerancia_minutos' => 10,
            'penalidad_por_fraccion' => 2,
            'activo' => true,
            'prioridad' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sensor_ocupado_no_permite_crear_reserva(): void
    {
        $this->fakeSensor(true);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva())
            ->assertRedirect(route('reservas.create', $this->espacio));

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_espacio_libre_con_auto_y_moto_activos_abre_formulario(): void
    {
        $this->fakeSensor(false);

        $this->actingAs($this->user)
            ->get(route('reservas.create', $this->espacio))
            ->assertOk()
            ->assertSee('Auto')
            ->assertSee('Moto')
            ->assertSee('vehiculo_tipo_id');
    }

    public function test_no_existen_tipos_activos_muestra_mensaje_controlado(): void
    {
        VehiculoTipo::query()->update(['activo' => false]);

        $this->actingAs($this->user)
            ->get(route('reservas.create', $this->espacio))
            ->assertRedirect(route('public.disponibilidad'))
            ->assertSessionHas('error', 'No existen tipos de vehiculo activos para este espacio.');
    }

    public function test_sensor_libre_y_sin_reservas_activas_permite_crear_reserva(): void
    {
        $this->fakeSensor(false);

        $response = $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva());

        $reserva = Reserva::firstOrFail();

        $response->assertRedirect(route('pagos.show', $reserva));
        $this->assertSame($this->user->id, $reserva->user_id);
        $this->assertSame('pendiente_pago', $reserva->estado);
        $this->assertDatabaseHas('pagos', [
            'reserva_id' => $reserva->id,
            'user_id' => $this->user->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_sensor_libre_pero_reserva_superpuesta_rechaza_nueva_reserva(): void
    {
        $this->fakeSensor(false);
        $this->crearReservaExistente('10:30:00', '11:30:00', 'pendiente_pago');

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva('11:00'))
            ->assertRedirect(route('reservas.create', $this->espacio))
            ->assertSessionHas('error', 'Ya existe una reserva para ese horario.');

        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_api_de_sensores_no_disponible_no_permite_reservar(): void
    {
        Http::fake([
            'http://200.234.236.154/api/sensores' => Http::response(['ok' => false], 500),
        ]);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva())
            ->assertRedirect(route('reservas.create', $this->espacio))
            ->assertSessionHas('error', 'El sensor no pudo confirmar que el espacio este libre.');

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_tipo_de_vehiculo_inactivo_enviado_manualmente_se_rechaza(): void
    {
        $this->vehiculoTipo->update(['activo' => false]);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva())
            ->assertSessionHasErrors('vehiculo_tipo_id');

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_tipo_activo_no_permitido_para_el_espacio_se_rechaza(): void
    {
        $camioneta = VehiculoTipo::create([
            'nombre' => 'Camioneta',
            'codigo' => 'camioneta',
            'activo' => true,
        ]);

        Tarifa::create([
            'vehiculo_tipo_id' => $camioneta->id,
            'nombre' => 'Tarifa camioneta por hora',
            'tipo_tarifa' => 'por_hora',
            'monto_base' => 0,
            'monto_por_hora' => 8,
            'monto_por_fraccion' => 4,
            'minutos_fraccion' => 30,
            'tiempo_minimo_minutos' => 60,
            'tolerancia_minutos' => 10,
            'penalidad_por_fraccion' => 4,
            'activo' => true,
            'prioridad' => 1,
        ]);

        $this->fakeSensor(false);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva('10:30', $camioneta->id))
            ->assertRedirect(route('reservas.create', $this->espacio))
            ->assertSessionHas('error', 'El tipo de vehiculo no esta permitido para este espacio.');

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_usuario_no_autenticado_vuelve_al_espacio_despues_del_login(): void
    {
        $response = $this->get(route('reservas.create', $this->espacio));

        $response->assertRedirect(route('login'));
        $this->assertStringContainsString(
            route('reservas.create', $this->espacio, false),
            session('url.intended')
        );

        $login = $this->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $login->assertRedirect(route('reservas.create', $this->espacio, false));
    }

    public function test_dos_intentos_del_mismo_horario_solo_registran_una_reserva(): void
    {
        $this->fakeSensor(false);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva())
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva())
            ->assertRedirect(route('reservas.create', $this->espacio))
            ->assertSessionHas('error', 'Ya existe una reserva para ese horario.');

        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_usuario_no_puede_ver_o_cancelar_reserva_ajena(): void
    {
        $otroUsuario = User::factory()->create([
            'role' => 'user',
            'activo' => true,
        ]);

        $reserva = $this->crearReservaExistente('10:30:00', '11:30:00', 'pendiente_pago', $otroUsuario);
        Pago::create([
            'reserva_id' => $reserva->id,
            'user_id' => $otroUsuario->id,
            'codigo_pago' => 'PAG-TEST',
            'metodo_pago' => 'simulado',
            'monto' => 5,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->user)
            ->get(route('pagos.show', $reserva))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('reservas.cancelar', $reserva))
            ->assertForbidden();
    }

    public function test_fecha_u_hora_pasada_muestra_error_de_validacion(): void
    {
        $this->fakeSensor(false);

        $this->actingAs($this->user)
            ->post(route('reservas.store', $this->espacio), $this->datosReserva('09:00'))
            ->assertSessionHasErrors('hora_inicio');

        $this->assertDatabaseCount('reservas', 0);
    }

    private function datosReserva(string $horaInicio = '10:30', ?int $vehiculoTipoId = null): array
    {
        return [
            'vehiculo_tipo_id' => $vehiculoTipoId ?? $this->vehiculoTipo->id,
            'fecha_reserva' => now('America/Lima')->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'duracion_minutos' => 60,
        ];
    }

    private function fakeSensor(bool $ocupado): void
    {
        Http::fake([
            'http://200.234.236.154/api/sensores' => Http::response([
                'ok' => true,
                'sensores' => [[
                    'id' => 1,
                    'codigo' => 'SENSOR_01',
                    'ocupado' => $ocupado,
                    'distancia_cm' => null,
                    'ultima_lectura_at' => now('UTC')->toJSON(),
                ]],
            ]),
        ]);
    }

    private function crearReservaExistente(
        string $horaInicio,
        string $horaFin,
        string $estado,
        ?User $user = null
    ): Reserva {
        $user ??= $this->user;

        return Reserva::create([
            'user_id' => $user->id,
            'espacio_id' => $this->espacio->id,
            'vehiculo_tipo_id' => $this->vehiculoTipo->id,
            'tarifa_id' => Tarifa::first()->id,
            'tipo_vehiculo_nombre' => $this->vehiculoTipo->nombre,
            'tarifa_nombre' => 'Tarifa auto por hora',
            'tipo_tarifa' => 'por_hora',
            'codigo_reserva' => 'RES-TEST-' . uniqid(),
            'fecha_reserva' => now('America/Lima')->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_minutos' => 60,
            'tarifa_hora' => 5,
            'monto_total' => 5,
            'tolerancia_minutos' => 10,
            'penalidad_por_fraccion' => 3,
            'monto_penalidad' => 0,
            'estado' => $estado,
            'expires_at' => now('America/Lima')->addMinutes(10),
        ]);
    }
}
