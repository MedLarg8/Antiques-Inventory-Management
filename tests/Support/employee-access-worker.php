<?php

use App\Actions\ChangeStorageAreaAssignment;
use App\Actions\ManageEmployee;
use App\Enums\Role;
use App\Models\StorageArea;
use App\Models\User;
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
[, $actorId,$targetId,$operation,$areaId,$directory,$workerId] = $argv;
DB::connection()->getPdo();
$actor = User::findOrFail($actorId);
$target = User::findOrFail($targetId);
touch($directory.'/ready-'.$workerId);
$deadline = microtime(true) + 15;
while (! file_exists($directory.'/start')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }usleep(10000);
}
try {
    if ($operation === 'assign') {
        app(ChangeStorageAreaAssignment::class)->handle(StorageArea::findOrFail($areaId), (int) $targetId, null, $actor);
    } else {
        app(ManageEmployee::class)->access($actor, $target, $operation === 'demote' ? Role::StockEmployee : ($operation === 'promote' ? Role::Admin : null), $operation === 'deactivate' ? false : null);
    }
    echo 'changed';
} catch (ValidationException|HttpException) {
    echo 'conflict';
}
