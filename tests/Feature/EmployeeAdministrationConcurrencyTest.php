<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\EmployeeAudit;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EmployeeAdministrationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function races(): array
    {
        return ['two self-demotions' => ['demote'], 'cross deactivation' => ['deactivate'], 'promotion versus assignment' => ['assignment']];
    }

    #[DataProvider('races')]
    public function test_concurrent_changes_preserve_admin_and_assignment_invariants(string $mode): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Real multi-connection tests require PostgreSQL.');
        }
        $connection = config('database.connections.pgsql');
        $this->assertStringEndsWith('_test', $connection['database']);
        $a = User::factory()->create(['role' => Role::Admin]);
        $b = User::factory()->create(['role' => Role::Admin]);
        $employee = User::factory()->create();
        $area = StorageArea::factory()->create();
        $jobs = match ($mode) {
            'demote' => [[$a, $a, 'demote'], [$b, $b, 'demote']],
            'deactivate' => [[$a, $b, 'deactivate'], [$b, $a, 'deactivate']],
            default => [[$a, $employee, 'promote'], [$b, $employee, 'assign']],
        };
        $directory = storage_path('framework/testing/access-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $workers = [];
        try {
            foreach ($jobs as $index => [$actor,$target,$operation]) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/employee-access-worker.php'), (string) $actor->id, (string) $target->id, $operation, (string) $area->id, $directory, (string) $index], base_path(), $environment);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! file_exists($directory.'/ready-0') || ! file_exists($directory.'/ready-1')) {
                if (microtime(true) > $deadline || ! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    $this->fail('Workers failed: '.$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
                }
                usleep(10000);
            }
            touch($directory.'/start');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $results[] = trim($worker->getOutput());
            }
            sort($results);
            $this->assertSame(['changed', 'conflict'], $results);
            if ($mode !== 'assignment') {
                $this->assertSame(1, User::where('role', Role::Admin)->where('is_active', true)->count());
            } else {
                $employee->refresh();
                $assigned = StorageAreaAssignment::where('user_id', $employee->id)->whereNull('ended_at')->exists();
                $this->assertTrue(($employee->role === Role::StockEmployee && $assigned) || ($employee->role === Role::Admin && ! $assigned));
            }
            $this->assertSame(1, EmployeeAudit::count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (['ready-0', 'ready-1', 'start'] as $file) {
                if (file_exists($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }
}
