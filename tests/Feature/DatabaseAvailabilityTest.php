<?php

namespace Tests\Feature;

use App\Support\DatabaseAvailability;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class DatabaseAvailabilityTest extends TestCase
{
    public static function failure(string $state = '08006'): PDOException
    {
        $exception = new PDOException('secret-password SELECT sensitive_column FROM private_table C:/private/server.php');
        $exception->errorInfo = [$state, 7, 'secret-password'];

        return $exception;
    }

    public function test_health_remains_available_with_database_backed_sessions_and_cache(): void
    {
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        // Resolving a lazy connection is harmless; any query on this mock fails.
        DB::shouldReceive('connection')->andReturn(Mockery::mock(Connection::class));
        $this->get('/up')->assertOk()->assertExactJson(['status' => 'ok'])->assertCookieMissing(config('session.cookie'));
        $this->assertFalse($this->app['session.store']->isStarted());
    }

    public function test_healthy_readiness_queries_the_database_without_starting_a_session(): void
    {
        config(['session.driver' => 'database']);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')->with('select 1')->once()->andReturn([(object) ['value' => 1]]);
        DB::shouldReceive('connection')->andReturn($connection);
        $this->get('/ready/database')->assertOk()->assertExactJson(['status' => 'ready'])->assertCookieMissing(config('session.cookie'));
        $this->assertFalse($this->app['session.store']->isStarted());
    }

    public function test_readiness_returns_minimal_json_without_a_session_even_for_html_clients(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')->with('select 1')->once()->andThrow(self::failure());
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->get('/ready/database')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_json_clients_receive_a_safe_503_without_exception_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/json-outage-test', fn () => throw self::failure());
        $this->getJson('/json-outage-test')->assertStatus(503)->assertExactJson([
            'status' => 'unavailable',
            'message' => 'Service temporarily unavailable. Check the request status before retrying any submitted operation. No action has been queued.',
        ])->assertHeader('Retry-After', '60');
    }

    public function test_database_session_failure_blocks_protected_reads_and_writes_without_disclosure(): void
    {
        config(['app.debug' => true, 'session.driver' => 'database']);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('table')->andThrow(self::failure());
        DB::shouldReceive('connection')->andReturn($connection);

        foreach (['GET', 'POST'] as $method) {
            $response = $this->call($method, '/stock-requests');
            $response->assertStatus(503)->assertSee('No action has been queued.')->assertHeader('Retry-After', '60');
            foreach (['secret-password', 'sensitive_column', 'private_table', 'server.php', 'Stack trace', 'SQLSTATE'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
    }

    public function test_inertia_visit_keeps_outage_status_even_with_a_stale_asset_version(): void
    {
        Route::middleware('web')->get('/outage-test', fn () => throw self::failure());
        $this->get('/outage-test', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale', 'Accept' => 'text/html'])
            ->assertStatus(503)->assertSee('No action has been queued.')->assertHeaderMissing('X-Inertia-Location');
    }

    public function test_database_session_save_failure_replaces_a_success_response_with_503(): void
    {
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('useWritePdo')->andReturnSelf();
        $query->shouldReceive('find')->andReturn(null);
        $query->shouldReceive('insert')->once()->andThrow(self::failure());
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('table')->with(config('session.table'))->andReturn($query);
        DB::shouldReceive('connection')->andReturn($connection);
        Route::middleware('web')->get('/session-save-test', fn () => response('success-marker'));
        $this->get('/session-save-test')->assertStatus(503)->assertDontSee('success-marker')->assertSee('No action has been queued.');
    }

    public function test_unrelated_defects_are_not_classified_as_outages(): void
    {
        foreach (['42P01', '23505', '40001', '40P01'] as $state) {
            $this->assertNull(DatabaseAvailability::sqlState(self::failure($state)));
        }
        $this->assertNull(DatabaseAvailability::sqlState(new RuntimeException('Connection refused')));
        $wrapped = new QueryException('pgsql', 'select private_data', ['secret-password'], self::failure());
        $this->assertSame('08006', DatabaseAvailability::sqlState($wrapped));
        config(['app.debug' => false]);
        Route::get('/defect-test', fn () => throw new RuntimeException('Application defect'));
        $this->get('/defect-test')->assertStatus(500)->assertDontSee('No action has been queued.');
    }

    public function test_reporting_is_throttled_and_does_not_log_exception_or_request_secrets(): void
    {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('add')->with('database-outage:08006', true, 60)->twice()->andReturn(true, false);
        Cache::shouldReceive('store')->with('file')->twice()->andReturn($store);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->with('Database connection unavailable.', [
            'sqlstate' => '08006', 'driver' => config('database.connections.'.config('database.default').'.driver'),
        ]);
        Log::shouldReceive('channel')->with('database_outages')->once()->andReturn($logger);
        $handler = app(ExceptionHandler::class);
        $handler->report(self::failure());
        $handler->report(self::failure());
    }
}
