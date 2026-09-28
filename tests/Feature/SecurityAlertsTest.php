<?php

namespace Tests\Feature;

use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Services\SecurityEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_changes_are_flagged_without_secret_values(): void
    {
        config(['security.alert_email' => null]);
        Queue::fake();
        $user = User::factory()->create();
        $this->assertFalse((bool) DB::table('security_events')->latest('id')->first()->requires_attention);
        $user->update(['password' => 'private-value-test']);
        $event = DB::table('security_events')->latest('id')->first();
        $this->assertTrue((bool) $event->requires_attention);
        $this->assertStringNotContainsString('private-value-test', json_encode($event));
        Queue::assertNothingPushed();
    }

    public function test_only_owner_can_review_and_review_is_idempotent(): void
    {
        $owner = User::factory()->create(['role' => 'super_admin']);
        $event = SecurityEvents::record('Test', 1, 'updated', ['role'], true);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post("/admin/seguridad/$event/review")->assertForbidden();
        $this->flushSession();
        $this->actingAs($owner)->post("/admin/seguridad/$event/review")->assertRedirect();
        $this->post("/admin/seguridad/$event/review")->assertRedirect();
        $this->assertSame($owner->id, DB::table('security_events')->find($event)->reviewed_by);
        $this->assertSame(1, DB::table('security_events')->where('resource_type', 'SecurityEvent')->where('resource_id', $event)->count());
    }

    public function test_notification_job_uses_only_configured_destination_and_generic_content(): void
    {
        Notification::fake();
        config(['security.alert_email' => null]);
        $event = SecurityEvents::record('Test', 1, 'updated', ['password'], true);
        (new SendSecurityAlert($event))->handle();
        Notification::assertNothingSent();
        config(['security.alert_email' => 'security@example.test', 'mail.default' => 'smtp']);
        (new SendSecurityAlert($event))->handle();
        Notification::assertSentOnDemand(SecurityAlert::class, fn ($notification, $channels, $recipient) => $recipient->routes['mail'] === 'security@example.test' && $notification->eventId === $event);
    }

    public function test_local_configuration_cannot_pass_production_check(): void
    {
        $this->artisan('security:check-production')->assertFailed();
    }
}
