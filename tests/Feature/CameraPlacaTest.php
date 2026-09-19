<?php

namespace Tests\Feature;

use App\Models\ClienteVehiculo;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\User;
use App\Models\VehiculoTipo;
use App\Services\ClientePlacaService;
use App\Services\PlacaVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CameraPlacaTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create(['role' => 'operador', 'activo' => true]);
    }

    public function test_camera_and_client_information_are_staff_only(): void
    {
        $this->postJson(route('admin.placas.camara'))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']))->postJson(route('admin.placas.cliente'), ['placa' => 'ABC123'])->assertForbidden();
        $this->postJson(route('admin.placas.camara'))->assertForbidden();
        $this->get(route('admin.clientes-vehiculos.index'))->assertForbidden();
        $this->post(route('admin.clientes-vehiculos.store'), [])->assertForbidden();
    }

    public function test_registration_requires_review_normalizes_plate_and_rejects_duplicate(): void
    {
        $operator = $this->operator();
        $data = ['placa' => 'abc-123', 'nombre' => 'Cliente presencial', 'telefono' => '999111222'];
        $this->actingAs($operator)->post(route('admin.clientes-vehiculos.store'), $data)->assertSessionHasErrors('revisado');
        $this->post(route('admin.clientes-vehiculos.store'), $data + ['revisado' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cliente_vehiculos', ['placa' => 'ABC123', 'registrado_por' => $operator->id, 'nombre' => 'Cliente presencial']);
        $this->post(route('admin.clientes-vehiculos.store'), $data + ['revisado' => 1])->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('cliente_vehiculos', 1);
    }

    public function test_account_link_must_be_an_active_customer_and_deactivation_hides_identity(): void
    {
        $operator = $this->operator();
        $customer = User::factory()->create(['role' => 'user']);
        $data = ['placa' => 'ABC123', 'nombre' => 'Nombre escrito', 'email_cuenta' => $operator->email, 'revisado' => 1];
        $this->actingAs($operator)->post(route('admin.clientes-vehiculos.store'), $data)->assertSessionHasErrors('email_cuenta');
        $data['email_cuenta'] = $customer->email;
        $this->post(route('admin.clientes-vehiculos.store'), $data)->assertSessionHasNoErrors();
        $vehicle = ClienteVehiculo::firstOrFail();
        $this->postJson(route('admin.placas.cliente'), ['placa' => 'abc-123'])->assertOk()->assertJsonPath('coincidencia.cliente.nombre', $customer->name);
        $this->put(route('admin.clientes-vehiculos.update', $vehicle), $data + ['activo' => 0])->assertSessionHasNoErrors();
        $this->postJson(route('admin.placas.cliente'), ['placa' => 'ABC123'])->assertOk()->assertJsonPath('coincidencia.cliente', null);
    }

    public function test_frame_returns_matches_without_creating_tickets_or_replacing_photo_session(): void
    {
        Storage::fake('local');
        $operator = $this->operator();
        ClienteVehiculo::create(['placa' => 'ABC123', 'nombre' => 'Ana prueba', 'registrado_por' => $operator->id]);
        $this->mock(PlacaVisionService::class, function ($mock) {
            $mock->shouldReceive('analizar')->once()->andReturn(['version' => 1, 'preview' => '', 'elapsed_ms' => 500, 'candidates' => [
                ['text' => 'ABC123', 'detection_confidence' => .95, 'ocr_confidence' => .9, 'crop' => ''],
                ['text' => 'XYZ789', 'detection_confidence' => .92, 'ocr_confidence' => .9, 'crop' => ''],
            ]]);
        });
        $image = UploadedFile::fake()->createWithContent('frame.jpg', file_get_contents(base_path('tests/Fixtures/vision-blank.jpg')));
        $this->actingAs($operator)->withSession(['vision_prueba' => ['id' => 'unchanged']])->postJson(route('admin.placas.camara'), ['imagen' => $image])
            ->assertOk()->assertJsonPath('candidates.0.coincidencia.cliente.nombre', 'Ana prueba')
            ->assertJsonPath('candidates.1.coincidencia.registrado', false)->assertSessionHas('vision_prueba.id', 'unchanged');
        $this->assertSame([], Storage::disk('local')->allFiles('vision-temporal'));
        $this->assertDatabaseCount('estadias', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_frequency_counts_finished_vehicle_visits_and_reports_active_ticket(): void
    {
        $operator = $this->operator();
        $space = Espacio::create(['codigo' => 'QA01', 'estado_actual' => 'libre', 'activo' => true]);
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto']);
        for ($i = 1; $i <= 3; $i++) {
            Estadia::create(['placa' => 'ABC123', 'codigo_ticket' => 'T'.$i, 'espacio_id' => $space->id, 'vehiculo_tipo_id' => $type->id,
                'operador_ingreso_id' => $operator->id, 'hora_ingreso' => now()->subDays($i), 'hora_salida' => $i < 3 ? now()->subDays($i)->addHour() : null,
                'fuente_inicio' => 'manual', 'tarifa_snapshot' => []]);
        }
        $result = app(ClientePlacaService::class)->buscar('ABC123');
        $this->assertSame(2, $result['visitas']);
        $this->assertTrue($result['frecuente']);
        $this->assertSame('T3', $result['estadia_activa']['ticket']);
        $this->assertNull($result['cliente']); // History never guesses a person's identity.
    }

    public function test_manual_correction_validates_input_and_unknown_plate_returns_no_person(): void
    {
        $this->actingAs($this->operator())->postJson(route('admin.placas.cliente'), ['placa' => ['ABC123']])->assertUnprocessable();
        $this->postJson(route('admin.placas.cliente'), ['placa' => '???'])->assertUnprocessable();
        $this->postJson(route('admin.placas.cliente'), ['placa' => 'XYZ789'])->assertOk()->assertJsonPath('coincidencia.cliente', null);
        $this->get(route('admin.estadias.create', ['placa' => 'XYZ789']))->assertOk()->assertSee('value="XYZ789"', false);
        $this->assertDatabaseCount('estadias', 0);
    }
}
