<?php

namespace App\Services;

use App\Jobs\SendSecurityAlert;
use Illuminate\Support\Facades\DB;

class SecurityEvents
{
    public static function record(string $type, int $resourceId, string $action, array $fields, bool $attention = false): int
    {
        $id = DB::table('security_events')->insertGetId([
            'actor_id' => auth()->id(), 'resource_type' => $type, 'resource_id' => $resourceId,
            'action' => $action, 'changed_fields' => json_encode(array_values($fields)),
            'requires_attention' => $attention, 'created_at' => now(),
        ]);
        if ($attention && config('security.alert_email') && ! in_array(config('mail.default'), ['log', 'array'])) {
            SendSecurityAlert::dispatch($id)->afterCommit();
        }

        return $id;
    }
}
