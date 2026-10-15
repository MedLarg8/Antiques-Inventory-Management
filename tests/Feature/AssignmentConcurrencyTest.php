<?php

namespace Tests\Feature;

use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AssignmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function races(): array
    {
        return [
            'same area, database index' => [true, 'database'],
            'same employee, database index' => [false, 'database'],
            'same area, application action' => [true, 'action'],
            'same employee, application action' => [false, 'action'],
        ];
    }

    #[DataProvider('races')]
    public function test_parallel_connections_cannot_create_overlapping_assignments(bool $sameArea, string $mode): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Real multi-connection race tests require PostgreSQL.');
        }
        $connection = config('database.connections.pgsql');
        $this->assertStringEndsWith('_test', $connection['database'], 'Use a disposable test database.');
        $firstArea = StorageArea::factory()->create();
        $secondArea = $sameArea ? $firstArea : StorageArea::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = $sameArea ? User::factory()->create() : $firstUser;
        $directory = storage_path('framework/testing/race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array',
        ];
        $workers = [];
        try {
            foreach ([[$firstArea, $firstUser], [$secondArea, $secondUser]] as $index => [$area, $user]) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/assignment-worker.php'), (string) $area->id, (string) $user->id, $directory, (string) $index, $mode], base_path(), $environment);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! file_exists($directory.'/ready-0') || ! file_exists($directory.'/ready-1')) {
                if (microtime(true) > $deadline || ! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    $this->fail('Race workers did not reach the synchronization barrier: '.$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
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
            $this->assertSame(['conflict', 'created'], $results);
            $this->assertSame(1, StorageAreaAssignment::whereNull('ended_at')->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (['ready-0', 'ready-1', 'start'] as $filename) {
                if (file_exists($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
            }
            rmdir($directory);
        }
    }
}
