<?php

namespace Tests\Feature;

use App\Models\{Espacio, Reserva, Sensor, Tarifa, User, VehiculoTipo};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanoTest extends TestCase
{
    use RefreshDatabase;

    private function space(): Espacio
    {
        $type = VehiculoTipo::create(['nombre'=>'Auto','codigo'=>'auto','activo'=>true]);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Hora','tipo_tarifa'=>'por_hora','monto_por_hora'=>5,'activo'=>true]);
        $space = Espacio::create(['codigo'=>'E01','estado_actual'=>'libre','modo_monitoreo'=>'manual','activo'=>true]);
        $space->vehiculoTipos()->attach($type);
        return $space;
    }

    public function test_preloaded_status_does_not_query_again_per_space(): void
    {
        $space = $this->space();
        $user = User::factory()->create();
        Reserva::create(['user_id'=>$user->id, 'espacio_id'=>$space->id,
            'codigo_reserva'=>'RES-STATUS', 'fecha_reserva'=>today(),
            'hora_inicio'=>'10:00:00', 'hora_fin'=>'11:00:00',
            'estado'=>'pendiente_pago', 'expires_at'=>now()->addMinutes(10)]);
        $expected = $space->fresh()->estado_actual;
        $spaces = Espacio::conEstadoOperativo()->with('sensor')->get();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        foreach (range(1, 5) as $repeat) {
            $this->assertSame($expected, $spaces->first()->estado_actual);
        }
        $this->assertCount(0, \Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }

    public function test_public_map_uses_manual_state_and_does_not_expose_customer_details(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        $space = $this->space();
        $this->get(route('public.disponibilidad'))->assertOk()->assertDontSee('Ver en lista')->assertDontSee('data-zoom')->assertSee('quote-vehicle')->assertSee('data-available="1"', false)
            ->assertSee('href="/reservas/crear/'.$space->id.'"', false)
            ->assertSee('data-refresh="/disponibilidad/estado"', false)
            ->assertSee('data-quote-url="/disponibilidad/cotizar/'.$space->id.'"', false)
            ->assertHeader('Cache-Control', 'no-cache, private');
        $this->getJson(route('public.disponibilidad.estado'))->assertOk()->assertJsonPath('espacios.0.puede_reservar', true);
        $user = User::factory()->create(['name'=>'NombrePrivado']);
        Reserva::create(['user_id'=>$user->id,'espacio_id'=>$space->id,'codigo_reserva'=>'RES-PRIVADO','placa'=>'ABC123',
            'fecha_reserva'=>today(),'hora_inicio'=>'10:00:00','hora_fin'=>'11:00:00','estado'=>'pendiente_pago','expires_at'=>now()->addMinutes(10)]);
        $this->get(route('public.disponibilidad'))->assertOk()->assertDontSee('NombrePrivado')->assertDontSee('ABC123')->assertDontSee('RES-PRIVADO');
        $this->getJson(route('public.disponibilidad.estado'))->assertOk()->assertJsonPath('espacios.0.estado_visual','reservado')
            ->assertJsonPath('espacios.0.puede_reservar',false)->assertDontSee('NombrePrivado')->assertDontSee('ABC123');
        $this->actingAs(User::factory()->create(['role'=>'operador']))->get(route('admin.dashboard'))->assertOk()->assertSee('NombrePrivado')->assertSee('ABC123');
    }

    public function test_sensor_without_fresh_reading_is_not_reservable_and_manual_still_is(): void
    {
        $space = $this->space();
        $space->update(['modo_monitoreo'=>'sensor']);
        Sensor::create(['espacio_id'=>$space->id,'codigo_sensor'=>'S1','estado'=>'activo']);
        $this->getJson(route('public.disponibilidad.estado'))->assertOk()->assertJsonPath('espacios.0.puede_reservar',false);
        $this->actingAs(User::factory()->create())->get(route('reservas.create',$space))->assertRedirect(route('public.disponibilidad'));
        $space->update(['modo_monitoreo'=>'manual']);
        $this->getJson(route('public.disponibilidad.estado'))->assertOk()->assertJsonPath('espacios.0.puede_reservar',true);
        $space->update(['activo'=>false]);
        $this->getJson(route('public.disponibilidad.estado'))->assertJsonCount(0,'espacios');
    }
}
