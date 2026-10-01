<?php

namespace Tests\Feature;

use App\Models\{Espacio, Tarifa, VehiculoTipo};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_uses_current_tariff_and_validates_vehicle_and_duration(): void
    {
        $this->travelTo(now()->setTime(10,0));
        $type = VehiculoTipo::create(['nombre'=>'Auto','codigo'=>'auto','activo'=>true]);
        $space = Espacio::create(['codigo'=>'E01','activo'=>true,'modo_monitoreo'=>'manual','estado_actual'=>'libre']);
        $space->vehiculoTipos()->attach($type);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Hora','tipo_tarifa'=>'por_hora','monto_por_hora'=>5,'activo'=>true,'tiempo_minimo_minutos'=>60]);
        Tarifa::create(['vehiculo_tipo_id'=>$type->id,'nombre'=>'Noche','tipo_tarifa'=>'nocturna','monto_base'=>12,'monto_por_hora'=>0,'tiempo_minimo_minutos'=>480,'hora_inicio'=>'22:00:00','hora_fin'=>'06:00:00','activo'=>true]);
        $url = route('public.cotizar',$space);
        $this->get(route('public.disponibilidad'))->assertOk()
            ->assertViewHas('tarifasIniciales', fn ($prices) =>
                $prices[$type->id][60]['total'] === '5.00'
                && $prices[$type->id][120]['total'] === '10.00')
            ->assertSee('data-initial-quotes', false);
        $this->getJson($url.'?vehiculo_tipo_id='.$type->id.'&duracion_minutos=120')->assertOk()->assertJsonPath('total','10.00')->assertJsonPath('puede_reservar',true)->assertJsonPath('precio_unitario', 'S/ 5.00 por hora');
        $this->getJson($url.'?vehiculo_tipo_id=999&duracion_minutos=120')->assertStatus(422);
        $this->getJson($url.'?vehiculo_tipo_id='.$type->id.'&duracion_minutos=999')->assertStatus(422);
        $this->travelTo(now()->setTime(23,0));
        $this->getJson($url.'?vehiculo_tipo_id='.$type->id.'&duracion_minutos=60')->assertOk()->assertJsonPath('total','12.00')->assertJsonPath('tarifa','Noche')->assertJsonPath('precio_unitario', 'S/ 12.00 · Tarifa nocturna fija');
        $space->update(['activo'=>false]);
        $this->getJson($url.'?vehiculo_tipo_id='.$type->id.'&duracion_minutos=60')->assertNotFound();
    }
}
