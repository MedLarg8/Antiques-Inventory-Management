<?php

use App\Actions\ChangeStorageAreaAssignment;
use App\Actions\StockWorkflow;
use App\Models\StockRequest;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    exit(1);
}
[, $actorId,$requestId,$operation,$directory,$workerId,$replacementId] = $argv;
DB::connection()->getPdo();
$actor = User::findOrFail($actorId);
$request = StockRequest::findOrFail($requestId);
touch($directory.'/ready-'.$workerId);
$deadline = microtime(true) + 15;
while (! file_exists($directory.'/start')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }usleep(10000);
}
try {
    $workflow = app(StockWorkflow::class);
    if ($operation === 'execute') {
        $workflow->execute($actor, $request);
    } elseif ($operation === 'approve') {
        $workflow->approve($actor, $request);
    } elseif ($operation === 'reject') {
        $workflow->reject($actor, $request, 'Rejected in competing review.');
    } else {
        $area = StorageArea::findOrFail($request->storage_area_id);
        $expected = $area->currentAssignment()->sole()->id;
        app(ChangeStorageAreaAssignment::class)->handle($area, (int) $replacementId, $expected, $actor);
    }
    echo 'changed';
} catch (ValidationException|HttpException|AuthorizationException) {
    echo 'conflict';
}
