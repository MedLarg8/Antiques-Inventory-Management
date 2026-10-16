<?php

namespace App\Console\Commands;

use App\Models\ItemImage;
use App\Services\InventoryImages;
use Illuminate\Console\Command;

class PruneInventoryImages extends Command
{
    protected $signature = 'inventory:prune-images';

    protected $description = 'Retry private file cleanup for removed inventory images';

    public function handle(InventoryImages $images): int
    {
        $failed = 0;
        foreach (ItemImage::onlyTrashed()->lazyById() as $image) {
            if (! $images->prune($image)) {
                $failed++;
            }
        }
        $this->info("Cleanup finished; {$failed} image records need retry.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
