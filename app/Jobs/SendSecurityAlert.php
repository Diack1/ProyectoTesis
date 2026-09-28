<?php

namespace App\Jobs;

use App\Notifications\SecurityAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendSecurityAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(public int $eventId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $destination = config('security.alert_email');
        if (! $destination || ! filter_var($destination, FILTER_VALIDATE_EMAIL)
            || in_array(config('mail.default'), ['log', 'array'])) {
            return;
        }
        $event = DB::table('security_events')->find($this->eventId);
        if ($event && $event->requires_attention && ! $event->reviewed_at) {
            Notification::route('mail', $destination)->notify(new SecurityAlert($this->eventId));
        }
    }
}
