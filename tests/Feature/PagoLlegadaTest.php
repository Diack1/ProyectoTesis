<?php

namespace Tests\Feature;

use App\Models\{Espacio, Estadia, Pago, Reserva, Tarifa, User, VehiculoTipo};
use App\Services\{LiquidacionService, ReservaService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PagoLlegadaTest extends TestCase
{
    use RefreshDatabase;
    private User $cliente;
    private User $staff;
    private Reserva $reserva;
    private array $entrada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfDay()->addHours(9));
        $this->cliente = User::factory()->create();
        $this->staff = User::factory()->create(['role' => 'operador']);
        $tipo = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        Tarifa::create(['vehiculo_tipo_id' => $tipo->id, 'nombre' => 'Hora', 'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5,
            'tolerancia_minutos' => 5, 'penalidad_por_fraccion' => 5, 'minutos_fraccion' => 60, 'activo' => true]);
        $espacio = Espacio::create(['codigo' => 'T1', 'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual']);
        $espacio->vehiculoTipos()->attach($tipo);
        $this->actingAs($this->cliente)->post(route('reservas.store', $espacio), ['placa' => 'ABC123', 'vehiculo_tipo_id' => $tipo->id, 'duracion_minutos' => 60])->assertSessionHasNoErrors();
        $this->reserva = Reserva::firstOrFail();
        $this->entrada = ['placa' => 'ABC123', 'espacio_id' => $espacio->id, 'vehiculo_tipo_id' => $tipo->id,
            'reserva_id' => $this->reserva->id, 'metodo_pago' => 'efectivo', 'efectivo_recibido' => 5, 'pago_verificado' => 1];
    }

    public function test_qr_is_private_signed_read_only_and_old_online_upload_is_blocked(): void
    {
        $this->assertTrue($this->reserva->pago_al_llegar);
        $this->get(route('reservas.pase', $this->reserva))->assertOk()->assertSee('data:image/svg+xml;base64,', false)->assertSee('ABC123');
        $this->post(route('pagos.enviar', $this->reserva), [])->assertStatus(409);
        $url = URL::signedRoute('admin.reservas.escanear', $this->reserva);
        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('reservas.pase', $this->reserva))->assertForbidden();
        $this->get(route('reservas.tiempo', $this->reserva))->assertForbidden();
        $this->actingAs($this->staff)->get($url)->assertRedirect(route('admin.estadias.create', ['reserva' => $this->reserva->id]));
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->get(route('admin.estadias.create', ['reserva' => $this->reserva->id]))->assertOk()->assertSee('Cobro al llegar');
        $this->assertSame(0, Estadia::count());
        $this->assertSame(0, Pago::where('estado', 'aprobado')->count());
    }

    public function test_cash_entry_is_atomic_and_cannot_be_repeated(): void
    {
        $this->actingAs($this->staff);
        $this->post(route('admin.estadias.store'), array_diff_key($this->entrada, ['pago_verificado' => 1]))->assertSessionHasErrors('pago_verificado');
        $this->post(route('admin.estadias.store'), array_replace($this->entrada, ['efectivo_recibido' => 4]))->assertSessionHasErrors('efectivo_recibido');
        $this->assertSame(0, Estadia::count());
        $this->assertSame('pendiente', Pago::first()->estado);
        $this->post(route('admin.estadias.store'), $this->entrada)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('confirmada', $this->reserva->fresh()->estado);
        $this->assertNull($this->reserva->fresh()->expires_at);
        $this->assertSame('5.00', Estadia::first()->monto_adelantado);
        $this->post(route('admin.estadias.store'), $this->entrada)->assertSessionHasErrors();
        $this->assertSame(1, Estadia::count());
        $this->assertSame(1, Pago::count());
        $this->assertSame('aprobado', Pago::first()->estado);
    }

    public function test_failure_after_payment_rolls_back_both_payment_and_entry(): void
    {
        Estadia::creating(fn () => throw new \RuntimeException('Simulated insert failure'));
        try {
            $this->withoutExceptionHandling()->actingAs($this->staff)->post(route('admin.estadias.store'), $this->entrada);
            $this->fail('Expected insert failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated insert failure', $e->getMessage());
        } finally { Estadia::flushEventListeners(); }
        $this->assertSame('pendiente_pago', $this->reserva->fresh()->estado);
        $this->assertSame('pendiente', Pago::first()->estado);
        $this->assertSame(0, Estadia::count());
    }

    public function test_expired_reservation_and_wrong_plate_cannot_enter(): void
    {
        $this->actingAs($this->staff)->post(route('admin.estadias.store'), array_replace($this->entrada, ['placa' => 'OTHER12']))->assertSessionHasErrors('reserva_id');
        $this->travel(16)->minutes();
        $this->post(route('admin.estadias.store'), $this->entrada)->assertSessionHasErrors('reserva_id');
        app(ReservaService::class)->expirarReservasPendientes();
        $this->assertSame('expirada', $this->reserva->fresh()->estado);
        $this->assertSame(0, Pago::where('estado', 'aprobado')->count());
    }

    public function test_electronic_payment_requires_unique_reference(): void
    {
        $this->actingAs($this->staff);
        $data = array_replace($this->entrada, ['metodo_pago' => 'yape']);
        $this->post(route('admin.estadias.store'), $data)->assertSessionHasErrors('referencia_pago');
        Pago::create(['codigo_pago' => 'OTHER', 'monto' => 5, 'metodo_pago' => 'yape', 'estado' => 'aprobado', 'operacion_unica' => 'yape:DUP123']);
        $this->post(route('admin.estadias.store'), $data + ['referencia_pago' => 'dup123'])->assertSessionHasErrors('referencia_pago');
        $this->post(route('admin.estadias.store'), $data + ['referencia_pago' => 'NEW123'])->assertSessionHasNoErrors();
        $this->assertSame('yape:NEW123', $this->reserva->pagoAprobado->operacion_unica);
    }

    public function test_counter_and_overtime_five_minute_boundaries_and_final_stop(): void
    {
        $this->actingAs($this->staff)->post(route('admin.estadias.store'), $this->entrada)->assertSessionHasNoErrors();
        $estadia = Estadia::firstOrFail();
        foreach ([3600 => 0, 3900 => 0, 3901 => 500, 7500 => 500, 7501 => 1000] as $seconds => $extra) {
            $this->travelTo($estadia->inicio_cobro->copy()->addSeconds($seconds));
            $this->actingAs($this->cliente)->getJson(route('reservas.tiempo', $this->reserva))->assertOk()->assertJson(['segundos' => $seconds, 'extra_centavos' => $extra, 'en_curso' => true]);
        }
        $estadia->update(['hora_salida' => now(), 'liquidado_hasta' => now()]);
        $this->travel(2)->hours();
        $this->getJson(route('reservas.tiempo', $this->reserva))->assertJson(['segundos' => 7501, 'extra_centavos' => 1000, 'en_curso' => false]);
    }
}
