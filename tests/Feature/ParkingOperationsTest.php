<?php

namespace Tests\Feature;

use App\Models\ConfiguracionPago;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\Reserva;
use App\Models\Sensor;
use App\Models\Tarifa;
use App\Models\User;
use App\Models\VehiculoTipo;
use App\Services\EstadiaService;
use App\Services\LiquidacionService;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParkingOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Espacio $space;

    private VehiculoTipo $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(10, 0, 0));
        $this->operator = User::factory()->create(['role' => 'operador']);
        $this->type = VehiculoTipo::create(['nombre' => 'Automóvil', 'codigo' => 'auto', 'activo' => true]);
        $this->space = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual']);
        $this->space->vehiculoTipos()->attach($this->type);
        Tarifa::create(['vehiculo_tipo_id' => $this->type->id, 'nombre' => 'Regular', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'monto_por_fraccion' => 2, 'minutos_fraccion' => 30, 'tiempo_minimo_minutos' => 60, 'tolerancia_minutos' => 10, 'penalidad_por_fraccion' => 3, 'activo' => true, 'prioridad' => 1]);
    }

    private function enter(?Reserva $r = null): Estadia
    {
        return app(EstadiaService::class)->ingresar(['placa' => 'ABC123', 'espacio_id' => $this->space->id, 'vehiculo_tipo_id' => $this->type->id, 'reserva_id' => $r?->id], $this->operator);
    }

    private function quote(Estadia $e): string
    {
        return Crypt::encryptString(json_encode(['id' => $e->id, 'inicio' => $e->inicio_cobro?->toIso8601String(), 'corte' => now()->toIso8601String()]));
    }

    private function reservation(): Reserva
    {
        $r = Reserva::create(['user_id' => User::factory()->create()->id, 'espacio_id' => $this->space->id, 'vehiculo_tipo_id' => $this->type->id, 'codigo_reserva' => 'RES-TEST', 'fecha_reserva' => today(), 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'duracion_minutos' => 60, 'monto_total' => 5, 'estado' => 'confirmada', 'tarifa_nombre' => 'Regular', 'tarifa_hora' => 5, 'tipo_tarifa' => 'por_hora', 'tolerancia_minutos' => 10, 'penalidad_por_fraccion' => 3, 'minutos_fraccion_snapshot' => 30, 'tolerancia_llegada_minutos' => 15]);
        Pago::create(['reserva_id' => $r->id, 'user_id' => $r->user_id, 'codigo_pago' => 'PAY-TEST', 'monto' => 5, 'estado' => 'aprobado', 'metodo_pago' => 'yape', 'pagado_at' => now()]);

        return $r;
    }

    public function test_walkin_ticket_cash_change_and_single_exit(): void
    {
        $this->actingAs($this->operator)->post(route('admin.estadias.store'), ['placa' => 'abc-123', 'espacio_id' => $this->space->id, 'vehiculo_tipo_id' => $this->type->id])->assertRedirect()->assertSessionHasNoErrors();
        $e = Estadia::firstOrFail();
        $this->assertSame('ABC123', $e->placa);
        $this->assertEquals($e->hora_ingreso, $e->inicio_cobro);
        $this->get(route('admin.estadias.show', $e))->assertOk()->assertSee('Cobro de salida');
        $this->get(route('admin.estadias.ticket', $e))->assertOk()->assertSee($e->codigo_ticket);
        $this->travel(75)->minutes(); // 65 minutes billed: hour 5 + fraction 2.
        $quote = $this->quote($e);
        $this->post(route('admin.estadias.salida', $e), ['cotizacion' => $quote, 'metodo_pago' => 'efectivo', 'efectivo_recibido' => 10, 'pago_verificado' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame('7.00', $e->saldo_cobrado);
        $this->assertSame('3.00', $e->vuelto);
        $this->assertNull($e->placa_activa);
        $this->assertNull($e->espacio_activo_id);
        $this->assertSame('libre', $this->space->fresh()->estado_actual);
        $this->assertNull($e->pagoSalida->reserva_id);
        $this->get(route('admin.estadias.show', $e))->assertOk()->assertSee('Salida registrada');
        $this->post(route('admin.estadias.salida', $e), ['cotizacion' => $quote, 'metodo_pago' => 'efectivo', 'efectivo_recibido' => 10, 'pago_verificado' => 1])->assertSessionHasErrors('salida');
        $this->assertSame(1, Pago::whereNotNull('estadia_id')->count());
    }

    public function test_cash_requires_verified_sufficient_payment_and_fresh_quote(): void
    {
        $e = $this->enter();
        $this->actingAs($this->operator);
        $payload = ['cotizacion' => $this->quote($e), 'metodo_pago' => 'efectivo', 'efectivo_recibido' => 2];
        $this->post(route('admin.estadias.salida', $e), $payload)->assertSessionHasErrors('pago_verificado');
        $payload['pago_verificado'] = 1;
        $this->post(route('admin.estadias.salida', $e), $payload)->assertSessionHasErrors('efectivo_recibido');
        $this->travel(6)->minutes();
        $payload['efectivo_recibido'] = 20;
        $this->post(route('admin.estadias.salida', $e), $payload)->assertSessionHasErrors('salida');
        $this->assertNull($e->fresh()->hora_salida);
        $this->assertSame(0, Pago::count());
    }

    public function test_transfer_operation_cannot_be_reused_from_online_payment(): void
    {
        $r = $this->reservation();
        $r->pagoAprobado->update(['operacion_unica' => 'yape:12345']);
        $e = $this->enter($r);
        $this->travel(80)->minutes();
        $payload = ['cotizacion' => $this->quote($e), 'metodo_pago' => 'yape', 'referencia_pago' => '12345', 'pago_verificado' => 1];
        $this->actingAs($this->operator)->post(route('admin.estadias.salida', $e), $payload)->assertSessionHasErrors('referencia_pago');
        $payload['referencia_pago'] = '99999';
        $this->post(route('admin.estadias.salida', $e), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('3.00', $e->fresh()->saldo_cobrado);
        $this->assertSame('8.00', $e->fresh()->monto_total);
        $this->assertSame('finalizada', $r->fresh()->estado);
    }

    public function test_prepaid_arrival_not_expired_and_no_second_charge_within_time(): void
    {
        $r = $this->reservation();
        $e = $this->enter($r);
        $this->travel(65)->minutes();
        $this->assertSame(0, app(ReservaService::class)->procesarInasistencias());
        $this->actingAs($r->usuario)->post(route('reservas.solicitarReembolso', $r))->assertRedirect();
        $this->assertSame(0, Reembolso::count());
        $this->actingAs($this->operator)->post(route('admin.estadias.salida', $e), ['cotizacion' => $this->quote($e)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.00', $e->fresh()->saldo_cobrado);
        $this->assertSame(1, Pago::count());
    }

    public function test_no_show_releases_space_and_only_requests_manual_refund_once(): void
    {
        $r = $this->reservation();
        $this->travel(15)->minutes();
        $this->assertSame(0, app(ReservaService::class)->procesarInasistencias());
        $this->travel(1)->minutes();
        $this->assertSame(1, app(ReservaService::class)->procesarInasistencias());
        $this->assertSame('reembolso_solicitado', $r->fresh()->estado);
        $this->assertSame('aprobado', $r->pagoAprobado->estado);
        $this->assertSame('libre', $this->space->fresh()->estado_actual);
        $this->assertSame('solicitado', Reembolso::first()->estado);
        $this->assertSame(0, app(ReservaService::class)->procesarInasistencias());
        $this->assertSame(1, Reembolso::count());
    }

    public function test_sensor_starts_once_and_free_reading_does_not_checkout(): void
    {
        $this->space->update(['modo_monitoreo' => 'sensor']);
        Sensor::create(['espacio_id' => $this->space->id, 'codigo_sensor' => 'A02-1', 'tipo_sensor' => 'ultrasonico', 'estado' => 'activo']);
        $e = $this->enter();
        $this->assertNull($e->inicio_cobro);
        $this->assertSame('ocupado', $this->space->fresh()->estado_actual);
        Sanctum::actingAs($this->operator);
        $this->travel(3)->minutes();
        $payload = ['codigo_sensor' => 'A02-1', 'estado' => 'ocupado', 'distancia_cm' => 50];
        $this->postJson('/api/sensores/ocupacion', $payload)->assertCreated();
        $inicio = $e->fresh()->inicio_cobro;
        $this->travel(3)->minutes();
        $this->postJson('/api/sensores/ocupacion', $payload)->assertCreated();
        $this->assertEquals($inicio, $e->fresh()->inicio_cobro);
        $payload['estado'] = 'libre';
        $this->postJson('/api/sensores/ocupacion', $payload)->assertCreated();
        $this->assertNull($e->fresh()->hora_salida);
        $this->assertSame('ocupado', $this->space->fresh()->estado_actual);
    }

    public function test_active_ticket_blocks_duplicate_entry_manual_release_and_customer_access(): void
    {
        $e = $this->enter();
        $this->actingAs($this->operator)->post(route('admin.estadias.store'), ['placa' => 'DEF456', 'espacio_id' => $this->space->id, 'vehiculo_tipo_id' => $this->type->id])->assertSessionHasErrors('espacio_id');
        $this->post(route('admin.espacios.estado', $this->space), ['estado' => 'libre'])->assertSessionHasErrors('estado');
        $this->actingAs(User::factory()->create())->get(route('admin.estadias.show', $e))->assertForbidden();
    }

    public function test_operation_pages_render_for_staff(): void
    {
        $this->enter();
        $this->actingAs($this->operator);
        foreach (['admin.dashboard', 'admin.estadias.index', 'admin.estadias.create', 'admin.monitoreo.index', 'admin.reservas.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_sensor_contingency_requires_reason_and_cannot_restart_time(): void
    {
        $this->space->update(['modo_monitoreo' => 'sensor']);
        Sensor::create(['espacio_id' => $this->space->id, 'codigo_sensor' => 'S1', 'tipo_sensor' => 'ultrasonico', 'estado' => 'activo']);
        $e = $this->enter();
        $this->travel(4)->minutes();
        $this->actingAs($this->operator);
        $this->get(route('admin.estadias.show', $e))->assertOk()->assertSee('Esperando al sensor');
        $this->post(route('admin.estadias.inicio-manual', $e), ['inicio_cobro' => now()->toDateTimeString(), 'motivo' => 'Falla'])->assertSessionHasErrors('motivo');
        $data = ['inicio_cobro' => now()->subMinute()->toDateTimeString(), 'motivo' => 'Sensor sin comunicación; vehículo verificado en el espacio.'];
        $this->post(route('admin.estadias.inicio-manual', $e), $data)->assertSessionHasNoErrors();
        $this->assertSame('manual_contingencia', $e->fresh()->fuente_inicio);
        $this->assertSame($this->operator->id, $e->fresh()->inicio_confirmado_por);
        $this->post(route('admin.estadias.inicio-manual', $e), $data)->assertSessionHasErrors('inicio_cobro');
    }

    public function test_tariff_snapshot_survives_price_changes_and_midnight(): void
    {
        $this->travelTo(now()->setTime(23, 30));
        $e = $this->enter();
        Tarifa::query()->update(['monto_por_hora' => 100, 'monto_por_fraccion' => 100]);
        $this->travel(70)->minutes();
        $cost = app(LiquidacionService::class)->calcular($e, now());
        $this->assertSame(70, $cost['minutos']);
        $this->assertSame(500, $cost['saldo_centavos']);
        $this->travel(1)->seconds();
        $cost = app(LiquidacionService::class)->calcular($e, now());
        $this->assertSame(700, $cost['saldo_centavos']);
    }

    public function test_client_and_admin_pages_render_with_paid_and_no_show_reservations(): void
    {
        $r = $this->reservation();
        $this->actingAs($r->usuario);
        $this->get(route('reservas.index'))->assertOk()->assertSee('Te esperamos');
        $this->actingAs($this->operator)->get(route('admin.estadias.create', ['reserva' => $r->id]))->assertOk()->assertSee('Llegada con reserva');
        $this->travel(16)->minutes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.reservas.index'))->assertOk()->assertSee('Inasistencia')->assertSee('Registrar devolución realizada');
        $this->actingAs($r->usuario)->get(route('reservas.index'))->assertOk()->assertSee('espacio fue liberado');
    }

    public function test_quote_is_bound_to_its_ticket_and_cannot_be_tampered(): void
    {
        $e = $this->enter();
        $this->actingAs($this->operator);
        $quote = Crypt::encryptString(json_encode(['id' => $e->id + 1, 'inicio' => $e->inicio_cobro->toIso8601String(), 'corte' => now()->toIso8601String()]));
        foreach ([$quote, 'alterado'] as $value) {
            $this->post(route('admin.estadias.salida', $e), ['cotizacion' => $value, 'metodo_pago' => 'efectivo', 'efectivo_recibido' => 10, 'pago_verificado' => 1])->assertSessionHasErrors('salida');
        }
        $this->assertNull($e->fresh()->hora_salida);
    }

    public function test_reservation_confirmation_explains_arrival_and_saves_settings(): void
    {
        ConfiguracionPago::create(['minutos_pago' => 10, 'tolerancia_llegada' => 12]);
        $this->actingAs(User::factory()->create());
        $data = ['placa' => 'ABC123', 'vehiculo_tipo_id' => $this->type->id, 'fecha_reserva' => today()->toDateString(), 'hora_inicio' => '11:00', 'duracion_minutos' => 60];
        $this->get(route('reservas.create', $this->space))->assertOk();
        $this->post(route('reservas.confirmar', $this->space), $data)->assertOk()->assertSee('Llegada prevista')->assertSee('12 minutos');
        $this->post(route('reservas.store', $this->space), $data)->assertSessionHasNoErrors();
        $r = Reserva::firstOrFail();
        $this->assertSame(12, $r->tolerancia_llegada_minutos);
        $this->assertSame(30,$r->minutos_fraccion_snapshot);
    }
}
