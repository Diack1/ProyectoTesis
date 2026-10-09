<?php

namespace Tests\Feature;

use App\Models\{ClienteVehiculo, Espacio, Estadia, Pago, Reserva, Tarifa, User, VehiculoTipo};
use App\Services\ClientePlacaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaPlacaTest extends TestCase
{
    use RefreshDatabase;

    public function test_continue_to_payment_is_pending_without_mail_and_repeated_submit_does_not_duplicate(): void
    {
        $type = VehiculoTipo::create(['nombre'=>'Auto', 'codigo'=>'auto', 'activo'=>true]);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id, 'nombre'=>'Hora', 'tipo_tarifa'=>'por_hora', 'monto_por_hora'=>5, 'activo'=>true]);
        $space = Espacio::create(['codigo'=>'E01', 'estado_actual'=>'libre', 'activo'=>true, 'modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $user = User::factory()->create();
        config(['mail.default'=>'resend', 'security.alert_email'=>'owner@example.test']);
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($user);
        $data = ['placa'=>'ABC123', 'vehiculo_tipo_id'=>$type->id, 'duracion_minutos'=>60];
        $this->post(route('reservas.store', $space), $data)->assertRedirect(route('pagos.show', 1))->assertSessionHasNoErrors();
        $this->post(route('reservas.store', $space), $data)->assertRedirect(route('public.disponibilidad'));
        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame('pendiente_pago', Reserva::first()->estado);
        $this->assertSame('pendiente', Pago::first()->estado);
        $this->assertNull(Pago::first()->pagado_at);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_failed_pending_payment_insert_rolls_back_the_reservation(): void
    {
        $type = VehiculoTipo::create(['nombre'=>'Auto', 'codigo'=>'auto', 'activo'=>true]);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id, 'nombre'=>'Hora', 'tipo_tarifa'=>'por_hora', 'monto_por_hora'=>5, 'activo'=>true]);
        $space = Espacio::create(['codigo'=>'E01', 'estado_actual'=>'libre', 'activo'=>true, 'modo_monitoreo'=>'manual']);
        $space->vehiculoTipos()->attach($type);
        $this->actingAs(User::factory()->create())->withoutExceptionHandling();
        $event = 'eloquent.creating: '.Pago::class;
        \Illuminate\Support\Facades\Event::listen($event, function () { throw new \RuntimeException('Simulated payment persistence failure'); });
        try {
            $this->post(route('reservas.store', $space), ['placa'=>'ABC123', 'vehiculo_tipo_id'=>$type->id, 'duracion_minutos'=>60]);
            $this->fail('The persistence error must not be reported as a successful payment.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated payment persistence failure', $e->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertDatabaseCount('reservas', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertSame('libre', $space->fresh()->estado_actual);
    }

    public function test_reservation_filters_and_counts_only_include_the_current_customer(): void
    {
        $user = User::factory()->create();
        $this->reservation(['user_id'=>$user->id, 'estado'=>'finalizada', 'placa'=>'OWN123']);
        $this->reservation(['user_id'=>$user->id, 'estado'=>'cancelada', 'placa'=>'CANCEL1']);
        $this->reservation(['estado'=>'finalizada', 'placa'=>'PRIVATE1']);
        $this->actingAs($user)->get(route('reservas.index',['estado'=>'finalizadas']))
            ->assertOk()->assertSee('OWN123')->assertDontSee('CANCEL1')->assertDontSee('PRIVATE1')
            ->assertViewHas('conteos', fn ($counts) => $counts['todas'] === 2 && $counts['finalizadas'] === 1 && $counts['canceladas'] === 1);
        $this->get(route('reservas.index',['estado'=>'canceladas']))->assertOk()->assertSee('CANCEL1')->assertDontSee('OWN123');
    }

    public function test_review_cannot_read_a_draft_belonging_to_another_user(): void
    {
        $space = Espacio::create(['codigo'=>'E01', 'estado_actual'=>'libre', 'activo'=>true]);
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->withSession(['reservation_review.'.$space->id => [
            'user_id'=>$owner->id, 'expires_at'=>now()->addMinutes(30)->timestamp,
            'data'=>['placa'=>'PRIVATE123'],
        ]])->get(route('reservas.revisar', $space))->assertRedirect(route('reservas.create', $space))->assertDontSee('PRIVATE123');
        $this->flushSession();
        $this->get(route('reservas.revisar', $space))->assertRedirect(route('reservas.create', $space));
    }

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
        $this->post(route('reservas.confirmar', $space), $data)->assertStatus(303)->assertRedirect(route('reservas.revisar', $space));
        $this->get(route('reservas.revisar', $space))->assertOk()
            ->assertSee('name="placa" value="ABC123"', false);
        $this->get(route('reservas.revisar', $space))->assertOk();
        $this->assertDatabaseCount('reservas', 0);
        $this->travel(31)->minutes();
        $this->get(route('reservas.revisar', $space))->assertRedirect(route('reservas.create', $space));
        $this->post(route('reservas.confirmar', $space), $data)->assertStatus(303);
        $this->post(route('reservas.store', $space), $data)->assertSessionHasNoErrors();
        $this->assertFalse(session()->has('reservation_review.'.$space->id));
        $r = Reserva::firstOrFail();
        $this->assertSame('ABC123', $r->placa);
        $this->assertSame('pendiente_pago', $r->estado);
        $this->get(route('pagos.show', $r))->assertOk()->assertSee('ABC123');
        $this->get(route('reservas.index'))->assertOk()->assertSee('ABC123');
    }

    public function test_twelve_hours_quote_review_and_store_use_server_price(): void
    {
        $this->travelTo(now('America/Lima')->startOfDay()->addHours(8));
        $type = VehiculoTipo::create(['nombre'=>'Auto', 'codigo'=>'auto', 'activo'=>true]);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id, 'nombre'=>'Hora', 'tipo_tarifa'=>'por_hora', 'monto_por_hora'=>5, 'activo'=>true]);
        $space = Espacio::create(['codigo'=>'E12', 'estado_actual'=>'libre', 'activo'=>true]);
        $space->vehiculoTipos()->attach($type);
        $data = ['vehiculo_tipo_id'=>$type->id, 'duracion_minutos'=>720, 'placa'=>'ABC123'];
        $this->getJson(route('public.cotizar', $space).'?'.http_build_query($data))
            ->assertOk()->assertJsonPath('total', '60.00');
        $prices = \App\Services\TarifaResumen::opciones([$type]);
        $this->assertCount(12, $prices[$type->id]);
        $this->assertSame('60.00', $prices[$type->id][720]['total']);
        $this->actingAs(User::factory()->create());
        $this->get(route('reservas.create', $space).'?'.http_build_query($data))->assertOk()->assertSee('12 horas');
        foreach ([0, 61, 780] as $invalid) {
            $bad = array_replace($data, ['duracion_minutos'=>$invalid]);
            $this->getJson(route('public.cotizar', $space).'?'.http_build_query($bad))->assertUnprocessable();
            foreach (['reservas.confirmar', 'reservas.store'] as $route) {
                $this->post(route($route, $space), $bad)->assertSessionHasErrors('duracion_minutos');
            }
        }
        $this->post(route('reservas.confirmar', $space), $data)->assertStatus(303);
        $this->get(route('reservas.revisar', $space))->assertOk()->assertSee('12 horas');
        $this->post(route('reservas.store', $space), $data)->assertSessionHasNoErrors();
        $r = Reserva::firstOrFail();
        $this->assertEquals(60, $r->monto_total);
        $this->assertEquals(720, $r->duracion_minutos);
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
            ->assertJsonPath('coincidencia.reservas.0.codigo', $r->codigo_corto)
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
        $this->assertSame($r->codigo_corto, app(ClientePlacaService::class)->buscar('ABC123')['reservas'][0]['codigo']);
        $this->travel(1)->seconds();
        $this->assertSame([], app(ClientePlacaService::class)->buscar('ABC123')['reservas']);
    }
}
