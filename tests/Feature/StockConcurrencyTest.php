<?php

namespace Tests\Feature;

use App\Actions\StockWorkflow;
use App\Enums\Role;
use App\Enums\StockRequestStatus;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestEvent;
use App\Models\StorageAreaAssignment;
use App\Models\StorageAreaInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function races(): array
    {
        return ['duplicate execution' => ['duplicate'], 'competing exits' => ['exits'], 'approval versus rejection' => ['review'], 'execution versus reassignment' => ['assignment']];
    }

    #[DataProvider('races')]
    public function test_concurrent_operations_preserve_stock_and_history(string $mode): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency tests require real PostgreSQL connections.');
        }
        $connection = config('database.connections.pgsql');
        $this->assertStringEndsWith('_test', $connection['database']);
        $assignment = StorageAreaAssignment::factory()->create();
        $employee = $assignment->user;
        $admin = User::factory()->create(['role' => Role::Admin]);
        $secondAdmin = User::factory()->create(['role' => Role::Admin]);
        $replacement = User::factory()->create();
        $item = Item::factory()->create();
        $balance = StorageAreaInventory::factory()->create(['storage_area_id' => $assignment->storage_area_id, 'item_id' => $item->id, 'quantity' => '10.000', 'initialized_at' => now()]);
        $workflow = app(StockWorkflow::class);
        $data = ['operation_type' => 'exit', 'reason' => 'Concurrent controlled outgoing operation', 'lines' => [['item_id' => $item->id, 'quantity' => '7.000']]];
        $first = $workflow->create($employee, $data);
        $workflow->submit($employee, $first);
        if ($mode !== 'review') {
            $workflow->approve($admin, $first);
        }
        $second = $first;
        if ($mode === 'exits') {
            $second = $workflow->create($employee, $data);
            $workflow->submit($employee, $second);
            $workflow->approve($admin, $second);
        }
        $jobs = match ($mode) {
            'review' => [[$admin, $first, 'approve'], [$secondAdmin, $first, 'reject']],
            'assignment' => [[$employee, $first, 'execute'], [$admin, $first, 'assign']],
            default => [[$employee, $first, 'execute'], [$employee, $second, 'execute']],
        };
        $directory = storage_path('framework/testing/stock-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $workers = [];
        try {
            foreach ($jobs as $index => [$actor,$request,$operation]) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/stock-worker.php'), (string) $actor->id, (string) $request->id, $operation, $directory, (string) $index, (string) $replacement->id], base_path(), $environment);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! file_exists($directory.'/ready-0') || ! file_exists($directory.'/ready-1')) {
                if (microtime(true) > $deadline || ! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    $this->fail('Workers did not reach barrier: '.$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
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
            if ($mode === 'assignment') {
                $this->assertContains($results, [['changed', 'changed'], ['changed', 'conflict']]);
                $this->assertSame($replacement->id, $assignment->storageArea->currentAssignment()->sole()->user_id);
                $completed = $first->fresh()->status === StockRequestStatus::Completed;
                $this->assertSame($completed ? '3.000' : '10.000', $balance->fresh()->quantity);
                $this->assertSame($completed ? 1 : 0, StockMovement::count());

                return;
            }
            $this->assertSame(['changed', 'conflict'], $results);
            if ($mode === 'review') {
                $this->assertContains($first->fresh()->status, [StockRequestStatus::Approved, StockRequestStatus::Rejected]);
                $this->assertSame('10.000', $balance->fresh()->quantity);
                $this->assertSame(0, StockMovement::count());
                $this->assertSame(1, StockRequestEvent::whereIn('event_type', ['approved', 'rejected'])->count());
                $this->assertNotNull($first->fresh()->reviewed_by);
            } else {
                $this->assertSame('3.000', $balance->fresh()->quantity);
                $this->assertSame(1, StockMovement::count());
                $this->assertSame(1, StockRequest::where('status', StockRequestStatus::Completed)->count());
                if ($mode === 'exits') {
                    $this->assertSame(1, StockRequest::where('status', StockRequestStatus::Approved)->count());
                }
                $this->assertSame(1, StockRequestEvent::where('event_type', 'executed')->count());
                $movement = StockMovement::sole();
                $this->assertSame('-7.000', $movement->quantity_delta);
                $this->assertSame('10.000', $movement->quantity_before);
                $this->assertSame('3.000', $movement->quantity_after);
            }
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
