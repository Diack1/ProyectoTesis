<?php

namespace Tests\Feature;

use App\Models\{ClienteVehiculo, Espacio, Estadia, Pago, Reserva, Tarifa, User, VehiculoTipo};
use App\Services\ClientePlacaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaPlacaTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_requires_plate_at_both_steps_and_preserves_normalized_plate(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        Tarifa::create(['vehiculo_tipo_id' => $type->id, 'nombre' => 'Hora', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'activo' => true]);
        $space = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'libre', 'activo' => true]);
        $space->vehiculoTipos()->attach($type);
        $this->actingAs(User::factory()->create());
        $data = ['vehiculo_tipo_id' => $type->id, 'fecha_reserva' => today()->toDateString(), 'hora_inicio' => '10:00', 'duracion_minutos' => 60];
        $this->get(route('reservas.create', $space))->assertOk()->assertSee('name="placa"', false);
        foreach (['reservas.confirmar', 'reservas.store'] as $route) {
            foreach ([null, ['ABC123'], '???', 'AB12', 'ABCDEFGHIJK'] as $invalid) {
                $this->post(route($route, $space), $data + ['placa' => $invalid])->assertSessionHasErrors('placa');
            }
        }
        $this->assertDatabaseCount('reservas', 0);
        $data['placa'] = ' abC-123 ';
        $this->post(route('reservas.confirmar', $space), $data)->assertOk()
            ->assertSee('name="placa" value="ABC123"', false);
        $this->post(route('reservas.store', $space), $data)->assertSessionHasNoErrors();
        $r = Reserva::firstOrFail();
        $this->assertSame('ABC123', $r->placa);
        $this->assertSame('pendiente_pago', $r->estado);
        $this->get(route('pagos.show', $r))->assertOk()->assertSee('ABC123');
        $this->get(route('reservas.index'))->assertOk()->assertSee('ABC123');
    }

    private function reservation(array $changes = []): Reserva
    {
        $space = Espacio::create(['codigo' => 'E'.(Espacio::count() + 1), 'estado_actual' => 'libre', 'activo' => true]);
        $r = Reserva::create(array_replace([
            'user_id' => User::factory()->create()->id, 'espacio_id' => $space->id,
            'codigo_reserva' => 'R'.(Reserva::count() + 1), 'placa' => 'ABC123',
            'fecha_reserva' => today(), 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00',
            'estado' => 'confirmada', 'monto_total' => 5, 'tolerancia_llegada_minutos' => 15,
        ], $changes));
        Pago::create(['reserva_id' => $r->id, 'user_id' => $r->user_id, 'codigo_pago' => 'P'.$r->id,
            'estado' => 'aprobado', 'metodo_pago' => 'yape', 'monto' => 5]);
        return $r;
    }

    public function test_exact_plate_reservation_notifies_without_customer_registry_or_writes(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $r = $this->reservation();
        $this->reservation(['user_id' => $r->user_id, 'placa' => 'XYZ789']);
        $operator = User::factory()->create(['role' => 'operador']);
        $this->actingAs($operator)->postJson(route('admin.placas.cliente'), ['placa' => 'abc-123'])
            ->assertOk()->assertJsonCount(1, 'coincidencia.reservas')
            ->assertJsonPath('coincidencia.reservas.0.codigo', $r->codigo_reserva)
            ->assertJsonPath('coincidencia.reservas.0.cliente', $r->usuario->name)
            ->assertJsonPath('coincidencia.cliente', null);
        ClienteVehiculo::create(['placa' => 'ABC123', 'user_id' => $r->user_id, 'nombre' => 'Cliente', 'registrado_por' => $operator->id]);
        $this->assertCount(1, app(ClientePlacaService::class)->buscar('ABC123')['reservas']);
        $this->assertDatabaseCount('estadias', 0);
        $this->assertDatabaseCount('pagos', 2);
        $this->assertSame('confirmada', $r->fresh()->estado);
        $this->get(route('admin.estadias.create', ['reserva' => $r->id]))->assertOk()->assertSee('value="ABC123"', false);
    }

    public function test_non_current_unpaid_legacy_and_consumed_reservations_do_not_notify(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        foreach ([['placa' => null], ['estado' => 'pendiente_pago'], ['estado' => 'cancelada'],
            ['inasistencia_at' => now()], ['fecha_reserva' => today()->addDay()],
            ['hora_inicio' => '09:44:59']] as $changes) {
            $this->reservation($changes);
        }
        $unpaid = $this->reservation();
        $unpaid->pagos()->update(['estado' => 'pendiente']);
        $used = $this->reservation();
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto']);
        Estadia::create(['reserva_id' => $used->id, 'placa' => 'ABC123', 'codigo_ticket' => 'T1',
            'espacio_id' => $used->espacio_id, 'vehiculo_tipo_id' => $type->id,
            'operador_ingreso_id' => User::factory()->create(['role' => 'operador'])->id,
            'hora_ingreso' => now(), 'fuente_inicio' => 'manual', 'tarifa_snapshot' => []]);
        $this->assertSame([], app(ClientePlacaService::class)->buscar('ABC123')['reservas']);
    }

    public function test_arrival_tolerance_includes_boundary_and_crosses_midnight(): void
    {
        $this->travelTo(now()->startOfDay()->addMinutes(5));
        $r = $this->reservation(['fecha_reserva' => today()->subDay(), 'hora_inicio' => '23:50:00']);
        $this->assertSame($r->codigo_reserva, app(ClientePlacaService::class)->buscar('ABC123')['reservas'][0]['codigo']);
        $this->travel(1)->seconds();
        $this->assertSame([], app(ClientePlacaService::class)->buscar('ABC123')['reservas']);
    }
}
