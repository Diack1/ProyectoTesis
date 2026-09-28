<?php

namespace Tests\Feature;

use App\Models\ConfiguracionPago;
use App\Models\Espacio;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\VehiculoTipo;
use App\Services\ReservaService;
use Database\Seeders\ParkeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParkeoPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ConfiguracionPago::create(['titular' => 'Parke’o', 'telefono' => '999999999', 'qr_yape' => 'qr.png', 'qr_plin' => 'qr.png', 'minutos_pago' => 10]);
        Storage::disk('local')->put('qr.png', 'test');
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('captura.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII='));
    }

    private function reservation(?User $user = null): Reserva
    {
        $user ??= User::factory()->create();
        $space = Espacio::create(['codigo' => 'E'.Str::random(8), 'estado_actual' => 'libre', 'activo' => true]);

        return Reserva::create(['user_id' => $user->id, 'espacio_id' => $space->id,
            'codigo_reserva' => 'RES-'.Str::uuid(), 'fecha_reserva' => now()->toDateString(),
            'hora_inicio' => now()->addHour()->format('H:i:s'), 'hora_fin' => now()->addHours(2)->format('H:i:s'),
            'estado' => 'pendiente_pago', 'monto_total' => 10, 'expires_at' => now()->addMinutes(10)]);
    }

    private function submit(Reserva $reserva, string $reference = '123456'): Pago
    {
        $this->actingAs($reserva->usuario)->post(route('pagos.enviar', $reserva), ['metodo_pago' => 'yape', 'referencia_pago' => $reference])
            ->assertRedirect()->assertSessionHasNoErrors();

        return $reserva->pagos()->latest('id')->firstOrFail();
    }

    public function test_submission_holds_reservation_until_human_approval(): void
    {
        $reserva = $this->reservation();
        $pago = $this->submit($reserva);
        $this->assertSame('pendiente', $pago->estado);
        $this->assertNull($reserva->fresh()->expires_at);
        $this->travel(11)->minutes();
        $this->assertSame(0, app(ReservaService::class)->expirarReservasPendientes());
        $operator = User::factory()->create(['role' => 'operador']);
        $this->actingAs($operator)->get(route('admin.pagos.pendientes'))->assertJson(['cantidad' => 1]);
        $this->get(route('admin.pagos.index'))->assertOk()->assertSee('123456');
        $this->post(route('admin.pagos.revisar', $pago), ['decision' => 'aprobado'])->assertRedirect();
        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame($operator->id, $pago->fresh()->revisado_por);
        $this->assertSame('libre', $reserva->espacio->fresh()->getRawOriginal('estado_actual'));
        $this->assertSame('reservado', $reserva->espacio->fresh()->estado_actual);
        $this->post(route('admin.pagos.revisar', $pago), ['decision' => 'rechazado', 'motivo_revision' => 'Doble clic'])->assertConflict();
    }

    public function test_rejection_keeps_history_and_allows_corrected_submission(): void
    {
        $reserva = $this->reservation();
        $pago = $this->submit($reserva);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('admin.pagos.revisar', $pago), ['decision' => 'rechazado'])->assertSessionHasErrors('motivo_revision');
        $this->post(route('admin.pagos.revisar', $pago), ['decision' => 'rechazado', 'motivo_revision' => 'No coincide el importe'])->assertRedirect();
        $this->assertTrue($reserva->fresh()->expires_at->isFuture());
        $this->actingAs($reserva->usuario)->get(route('pagos.show', $reserva))->assertOk()->assertSee('No coincide el importe');
        $new = $this->submit($reserva);
        $this->assertNotEquals($pago->id, $new->id);
        $this->assertSame('rechazado', $pago->fresh()->estado);
    }

    public function test_duplicate_reference_and_double_submission_are_rejected(): void
    {
        $first = $this->reservation();
        $this->submit($first);
        $this->post(route('pagos.enviar', $first), ['metodo_pago' => 'yape', 'referencia_pago' => 'other'])->assertSessionHasErrors();
        $second = $this->reservation();
        $this->actingAs($second->usuario)->post(route('pagos.enviar', $second), ['metodo_pago' => 'yape', 'referencia_pago' => '123456'])->assertSessionHasErrors('referencia_pago');
        $this->assertSame(1, Pago::count());
    }

    public function test_expired_reservation_and_empty_evidence_are_rejected(): void
    {
        $reserva = $this->reservation();
        $this->actingAs($reserva->usuario)->post(route('pagos.enviar', $reserva), ['metodo_pago' => 'yape'])->assertSessionHasErrors();
        $reserva->update(['expires_at' => now()->subMinute()]);
        $this->post(route('pagos.enviar', $reserva), ['metodo_pago' => 'yape', 'referencia_pago' => '123'])->assertSessionHasErrors('pago');
        $this->assertSame(0, Pago::count());
    }

    public function test_screenshot_only_is_private_and_not_auto_approved(): void
    {
        $reserva = $this->reservation();
        $this->actingAs($reserva->usuario)->post(route('pagos.enviar', $reserva), [
            'metodo_pago' => 'plin', 'comprobante' => $this->png(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $pago = $reserva->pagos()->firstOrFail();
        $this->assertSame('pendiente', $pago->estado);
        Storage::disk('local')->assertExists($pago->comprobante);
        $this->get(route('pagos.comprobante', $pago))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('pagos.comprobante', $pago))->assertForbidden();
        $this->post(route('admin.pagos.revisar', $pago), ['decision' => 'aprobado'])->assertForbidden();
        $this->post(route('pagos.enviar', $reserva), ['metodo_pago' => 'yape', 'referencia_pago' => '999'])->assertForbidden();
    }

    public function test_untrusted_file_is_not_stored(): void
    {
        $reserva = $this->reservation();
        $this->actingAs($reserva->usuario)->post(route('pagos.enviar', $reserva), [
            'metodo_pago' => 'yape', 'comprobante' => UploadedFile::fake()->create('script.php', 1, 'text/plain'),
        ])->assertSessionHasErrors('comprobante');
        $this->assertSame([], Storage::disk('local')->allFiles('comprobantes'));
    }

    public function test_operator_cannot_configure_business_or_create_staff(): void
    {
        $operator = User::factory()->create(['role' => 'operador']);
        $this->actingAs($operator)->get(route('admin.pagos.configuracion'))->assertForbidden();
        $this->get(route('superadmin.dashboard'))->assertForbidden();
        $this->get(route('admin.espacios.create'))->assertForbidden();
        $this->get(route('admin.monitoreo.index'))->assertOk();
        $operator->update(['activo' => false]);
        $this->get(route('admin.pagos.index'))->assertRedirect(route('login'));
    }

    public function test_owner_creates_reception_but_cannot_create_another_owner(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $data = ['name' => 'Operador', 'email' => 'operador@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin', 'activo' => 1];
        $this->actingAs($admin)->post(route('superadmin.admins.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $data['email'], 'role' => 'admin']);
        $data['email'] = 'otro@example.test';
        $data['role'] = 'super_admin';
        $this->post(route('superadmin.admins.store'), $data)->assertSessionHasErrors('role');
    }

    public function test_configuration_upload_and_pages_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->put(route('admin.pagos.configuracion.update'), ['titular' => 'Parke’o', 'telefono' => '999999999', 'minutos_pago' => 15,
                'qr_yape' => $this->png()])->assertSessionHasNoErrors();
        $this->get(route('admin.pagos.configuracion'))->assertOk()->assertSee('Parke’o');
        $this->get(route('pagos.qr', 'yape'))->assertOk();
        $this->assertSame(15, ConfiguracionPago::actual()->minutos_pago);
        $reserva = $this->reservation();
        $this->actingAs($reserva->usuario)->get(route('pagos.show', $reserva))->assertOk()->assertSee('Enviar para revisión');
    }

    public function test_seed_preserves_records_and_adds_thirty_spaces_without_fake_sensors(): void
    {
        Espacio::create(['codigo' => 'E01', 'estado_actual' => 'ocupado', 'activo' => true]);
        $this->seed(ParkeoSeeder::class);
        $this->seed(ParkeoSeeder::class);
        $this->assertSame(30, Espacio::count());
        $this->assertSame(15, Espacio::where('incluido_estudio', true)->count());
        $this->assertDatabaseHas('espacios', ['codigo' => 'E01', 'estado_actual' => 'ocupado']);
        $this->assertDatabaseCount('sensores', 0);
    }

    public function test_manual_space_can_be_saved_without_sensor_and_deactivation_preserves_history(): void
    {
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->post(route('admin.espacios.store'), [
            'codigo' => 'E30', 'estado_actual' => 'libre', 'modo_monitoreo' => 'manual', 'activo' => 1, 'vehiculo_tipo_ids' => [$type->id],
        ])->assertSessionHasNoErrors();
        $space = Espacio::where('codigo', 'E30')->firstOrFail();
        $this->get(route('admin.espacios.edit', $space))->assertOk();
        $this->delete(route('admin.espacios.destroy', $space))->assertRedirect();
        $this->assertDatabaseHas('espacios', ['id' => $space->id, 'activo' => false]);
    }

    public function test_pending_review_cannot_be_cancelled_and_expiry_preserves_physical_occupancy(): void
    {
        $reserva = $this->reservation();
        $this->submit($reserva);
        $this->post(route('reservas.cancelar', $reserva))->assertConflict();
        $other = $this->reservation();
        $other->espacio->update(['estado_actual' => 'ocupado']);
        $other->update(['expires_at' => now()->subMinute()]);
        app(ReservaService::class)->expirarReservasPendientes();
        $this->assertSame('ocupado', $other->espacio->fresh()->estado_actual);
    }

    public function test_reservation_uses_current_tariff_and_configured_payment_deadline(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        ConfiguracionPago::actual()->update(['minutos_pago' => 20]);
        $type = VehiculoTipo::create(['nombre' => 'Auto', 'codigo' => 'auto', 'activo' => true]);
        \App\Models\Tarifa::create(['vehiculo_tipo_id' => $type->id, 'nombre' => 'Hora auto',
            'tipo_tarifa' => 'por_hora', 'monto_por_hora' => 5, 'activo' => true]);
        $space = Espacio::create(['codigo' => 'E01', 'estado_actual' => 'libre', 'activo' => true]);
        $space->vehiculoTipos()->attach($type);
        $data = ['placa' => 'ABC123', 'vehiculo_tipo_id' => $type->id, 'fecha_reserva' => now()->toDateString(), 'hora_inicio' => '10:00', 'duracion_minutos' => 60];
        $this->actingAs(User::factory()->create())->post(route('reservas.store', $space), $data)
            ->assertSessionHasNoErrors()->assertRedirect();
        $reserva = Reserva::firstOrFail();
        $this->assertSame('5.00', $reserva->monto_total);
        $this->assertTrue($reserva->expires_at->equalTo(now()->addMinutes(20)));
        $this->assertSame('manual', $reserva->pagos()->firstOrFail()->metodo_pago);
        $this->assertSame('libre', $space->fresh()->getRawOriginal('estado_actual'));
        $this->assertSame('reservado', $space->fresh()->estado_actual);
        $this->actingAs(User::factory()->create())->post(route('reservas.store', $space), $data)->assertRedirect();
        $this->assertSame(1, Reserva::count());
    }

    public function test_legacy_simulated_payment_endpoint_is_removed(): void
    {
        $reserva = $this->reservation();
        $this->actingAs($reserva->usuario)->post('/reservas/'.$reserva->id.'/pago-simulado')->assertNotFound();
        $this->assertSame('pendiente_pago', $reserva->fresh()->estado);
    }
    public function test_immediate_arrival_clock_starts_at_approval_and_expires_to_manual_refund(): void
    {
        $this->travelTo(now()->setTime(10,0,0));
        $r=$this->reservation();
        $r->update(['reserva_inmediata'=>true,'tolerancia_llegada_minutos'=>15,'expires_at'=>null]);
        $p=Pago::create(['reserva_id'=>$r->id,'user_id'=>$r->user_id,'codigo_pago'=>'PAG-INMEDIATA','metodo_pago'=>'yape','monto'=>5,'estado'=>'pendiente','enviado_at'=>now()]);
        $this->travel(2)->hours();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.pagos.revisar',$p),['decision'=>'aprobado'])->assertRedirect();
        $r->refresh();
        $this->assertTrue($r->limite_llegada->equalTo(now()->addMinutes(15)));
        $this->assertSame(now()->format('H:i:s'),$r->hora_inicio);
        $this->travel(15)->minutes();
        $this->assertSame(0,app(ReservaService::class)->procesarInasistencias());
        $this->post(route('admin.pagos.revisar',$p),['decision'=>'aprobado'])->assertConflict();
        $this->travel(1)->seconds();
        $this->assertSame(1,app(ReservaService::class)->procesarInasistencias());
        $this->assertSame('reembolso_solicitado',$r->fresh()->estado);
        $this->assertDatabaseHas('reembolsos',['reserva_id'=>$r->id,'estado'=>'solicitado']);
    }

}
