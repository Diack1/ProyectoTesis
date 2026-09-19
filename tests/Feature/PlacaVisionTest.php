<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlacaVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlacaVisionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'operador', 'activo' => true]);
    }

    private function sampleResult(User $user): array
    {
        return ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'expires_at' => now()->addMinutes(30)->timestamp,
            'confirmed' => null, 'analysis' => ['version' => 1, 'preview' => '', 'elapsed_ms' => 500, 'truncated' => false,
                'candidates' => [['text' => 'ABC123', 'crop' => '', 'detection_confidence' => 0.9, 'ocr_confidence' => 0.7]]]];
    }

    public function test_only_active_staff_can_access_upload_and_confirmation(): void
    {
        $this->get(route('admin.placas.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))->get(route('admin.placas.index'))->assertForbidden();
        $this->post(route('admin.placas.analizar'))->assertForbidden();
        $this->post(route('admin.placas.confirmar'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'operador', 'activo' => false]))->get(route('admin.placas.index'))->assertRedirect(route('login'));
        $this->actingAs($this->staff())->get(route('admin.placas.index'))->assertOk()->assertSee('Reconocimiento de placas');
    }

    public function test_uploaded_image_is_private_removed_and_does_not_create_business_records(): void
    {
        Storage::fake('local');
        $user = $this->staff();
        $analysis = $this->sampleResult($user)['analysis'];
        $this->mock(PlacaVisionService::class, function ($mock) use ($analysis) {
            $mock->shouldReceive('analizar')->once()->andReturnUsing(function ($path) use ($analysis) {
                $this->assertFileExists($path);
                $this->assertStringContainsString('vision-temporal', $path);

                return $analysis;
            });
        });
        $this->actingAs($user)->post(route('admin.placas.analizar'), ['imagen' => UploadedFile::fake()->createWithContent('vehiculo.jpg', file_get_contents(base_path('tests/Fixtures/vision-blank.jpg')))])
            ->assertRedirect(route('admin.placas.index'))->assertSessionHas('vision_prueba.analysis.candidates.0.text', 'ABC123');
        $this->assertSame([], Storage::disk('local')->allFiles('vision-temporal'));
        $this->assertDatabaseCount('estadias', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_failed_inference_cleans_original_and_discards_stale_result(): void
    {
        Storage::fake('local');
        $user = $this->staff();
        $this->mock(PlacaVisionService::class, function ($mock) {
            $mock->shouldReceive('analizar')->once()->andThrow(ValidationException::withMessages(['imagen' => 'Motor no disponible.']));
        });
        $this->actingAs($user)->withSession(['vision_prueba' => $this->sampleResult($user)])
            ->from(route('admin.placas.index'))->post(route('admin.placas.analizar'), ['imagen' => UploadedFile::fake()->createWithContent('foto.jpg', file_get_contents(base_path('tests/Fixtures/vision-blank.jpg')))])
            ->assertSessionHasErrors('imagen')->assertSessionMissing('vision_prueba');
        $this->assertSame([], Storage::disk('local')->allFiles('vision-temporal'));
    }

    public function test_non_image_and_excessive_dimensions_are_rejected_before_inference(): void
    {
        $this->mock(PlacaVisionService::class)->shouldNotReceive('analizar');
        $this->actingAs($this->staff())->post(route('admin.placas.analizar'), ['imagen' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('imagen');
        $this->post(route('admin.placas.analizar'), ['imagen' => UploadedFile::fake()->createWithContent('grande.jpg', file_get_contents(base_path('tests/Fixtures/vision-wide.jpg')))])
            ->assertSessionHasErrors('imagen');
    }

    public function test_confirmation_requires_review_and_a_current_candidate_and_only_changes_session(): void
    {
        $user = $this->staff();
        $result = $this->sampleResult($user);
        $data = ['prueba_id' => $result['id'], 'candidato' => 0, 'placa' => 'abc-128'];
        $this->actingAs($user)->withSession(['vision_prueba' => $result])->post(route('admin.placas.confirmar'), $data)->assertSessionHasErrors('revisado');
        $data['revisado'] = '1';
        $this->post(route('admin.placas.confirmar'), $data)->assertRedirect(route('admin.placas.index'))->assertSessionHas('vision_prueba.confirmed.plate', 'ABC128');
        $this->assertDatabaseCount('estadias', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->post(route('admin.placas.confirmar'), array_replace($data, ['candidato' => 8]))->assertUnprocessable();
        $this->post(route('admin.placas.confirmar'), array_replace($data, ['prueba_id' => (string) Str::uuid()]))->assertConflict();
        $this->post(route('admin.placas.confirmar'), array_replace($data, ['placa' => '<script>']))->assertSessionHasErrors('placa');
        $this->post(route('admin.placas.confirmar'), array_replace($data, ['placa' => ['ABC123']]))->assertSessionHasErrors('placa');
    }

    public function test_expired_or_other_users_result_cannot_be_viewed_or_confirmed(): void
    {
        $user = $this->staff();
        $result = $this->sampleResult($user);
        $this->actingAs($user)->withSession(['vision_prueba' => $result]);
        $this->travel(31)->minutes();
        $this->post(route('admin.placas.confirmar'), ['prueba_id' => $result['id'], 'candidato' => 0, 'placa' => 'ABC123', 'revisado' => 1])->assertConflict();
        $this->withSession(['vision_prueba' => $result])->get(route('admin.placas.index'))->assertOk()->assertSessionMissing('vision_prueba');
        $this->withSession(['vision_prueba' => $this->sampleResult($this->staff())])->get(route('admin.placas.index'))->assertSessionMissing('vision_prueba');
    }

    public function test_empty_detection_has_no_confirmation_and_clear_removes_result(): void
    {
        $user = $this->staff();
        $result = $this->sampleResult($user);
        $result['analysis']['candidates'] = [];
        $this->actingAs($user)->withSession(['vision_prueba' => $result])->get(route('admin.placas.index'))
            ->assertOk()->assertSee('No se detectó una placa')->assertDontSee('Confirmar esta placa');
        $this->delete(route('admin.placas.limpiar'))->assertRedirect(route('admin.placas.index'))->assertSessionMissing('vision_prueba');
    }
}
