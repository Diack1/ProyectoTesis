<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresPaymentSchemaTest extends TestCase
{
    public function test_postgres_repair_preserves_state_validation_and_allows_pending_payments(): void
    {
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql');
        try {
            $connection = DB::connection();
            $schema = $connection->getSchemaBuilder();
            // Pretend uses the installed PostgreSQL grammar without a server.
            // Document why changing the type alone did not fix the old CHECK.
            $change = $connection->pretend(fn () => $schema->table('reservas', fn (Blueprint $t) => $t->string('estado')->change()));
            $this->assertStringNotContainsString('drop constraint', strtolower(implode(' ', array_column($change, 'query'))));
            $migration = require database_path('migrations/2026_10_09_000001_fix_postgres_reserva_estado_constraint.php');
            $queries = $connection->pretend(fn () => $migration->up());
            $sql = strtolower(implode(' ', array_column($queries, 'query')));
            $this->assertStringContainsString('drop constraint if exists reservas_estado_check', $sql);
            $this->assertStringContainsString('check (estado in', $sql);
            foreach (['pendiente', 'pendiente_pago', 'confirmada', 'cancelada', 'finalizada', 'expirada', 'reembolso_solicitado', 'reembolso_aprobado', 'reembolso_rechazado'] as $state) {
                $this->assertStringContainsString("'{$state}'", $sql);
            }
            $this->assertStringNotContainsString('delete ', $sql);
            $this->assertStringNotContainsString('update ', $sql);
        } finally {
            DB::setDefaultConnection($original);
        }
    }
}
