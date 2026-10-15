<?php

use App\Actions\ChangeStorageAreaAssignment;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Separate process and database connection for real PostgreSQL race tests.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    fwrite(STDERR, 'Race workers require a disposable _test database.');
    exit(1);
}

[, $areaId, $userId, $directory, $workerId, $mode] = $argv;
DB::connection()->getPdo();
touch($directory.'/ready-'.$workerId);
$deadline = microtime(true) + 15;
while (! file_exists($directory.'/start')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}

try {
    if ($mode === 'action') {
        app(ChangeStorageAreaAssignment::class)->handle(StorageArea::findOrFail($areaId), (int) $userId, null);
    } else {
        DB::transaction(function () use ($areaId, $userId): void {
            StorageAreaAssignment::create(['storage_area_id' => $areaId, 'user_id' => $userId, 'assigned_at' => now()]);
            usleep(250000);
        });
    }
    echo 'created';
} catch (UniqueConstraintViolationException|Illuminate\Validation\ValidationException) {
    echo 'conflict';
}
