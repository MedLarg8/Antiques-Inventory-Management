<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

class InventoryImages
{
    public function upload(Item|ItemAsset $owner, UploadedFile $file, User $user): void
    {
        $size = @getimagesize($file->getPathname());
        if (! $size || config('inventory.image_max_pixels') < $size[0] * $size[1] || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['image' => 'Use a valid JPEG, PNG or WebP within the image dimensions limit.']);
        }
        try {
            $decoded = ImageManager::usingDriver(Driver::class, decodeAnimation: false, strip: true)->decodePath($file->getPathname());
            $decoded->scaleDown(width: 1600, height: 1600);
            $preview = (string) $decoded->encodeUsingFormat(Format::WEBP, quality: 85);
            $decoded->scaleDown(width: 320, height: 320);
            $thumbnail = (string) $decoded->encodeUsingFormat(Format::WEBP, quality: 80);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['image' => 'This image could not be processed.']);
        }
        $diskName = config('inventory.image_disk');
        $disk = Storage::disk($diskName);
        $base = 'inventory-images/'.Str::uuid();
        $extension = match ($size['mime']) {
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'webp'
        };
        $paths = [$base.'/original.'.$extension, $base.'/preview.webp', $base.'/thumbnail.webp'];
        try {
            foreach ([$file->getContent(), $preview, $thumbnail] as $i => $content) {
                if (! $disk->put($paths[$i], $content, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Image storage write failed.');
                }
            }
            DB::transaction(function () use ($owner, $user, $file, $size, $paths, $diskName) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $owner->newQuery()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                $owner->images()->create([
                    'storage_disk' => $diskName, 'storage_path' => $paths[0], 'preview_path' => $paths[1], 'thumbnail_path' => $paths[2],
                    'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $size['mime'],
                    'file_size' => $file->getSize(), 'uploaded_by' => $user->id, 'is_primary' => ! $owner->images()->exists(),
                    'display_order' => (int) $owner->images()->max('display_order') + 1,
                ]);
            });
        } catch (Throwable $e) {
            try {
                if (! $disk->delete($paths)) {
                    Log::error('Inventory image upload compensation failed.', ['paths' => $paths]);
                }
            } catch (Throwable $cleanup) {
                report($cleanup);
            }
            report($e);
            throw ValidationException::withMessages(['image' => 'The upload could not be saved. Please retry.']);
        }
    }

    public function remove(ItemImage $image): void
    {
        DB::transaction(function () use ($image) {
            $owner = $image->owner();
            $owner->newQuery()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $image->refresh();
            $primary = $image->is_primary;
            $image->delete();
            if ($primary) {
                $owner->images()->first()?->update(['is_primary' => true]);
            }
        });
        $this->prune($image);
    }

    public function prune(ItemImage $image): bool
    {
        if (! $image->trashed()) {
            return false;
        }
        try {
            $disk = Storage::disk($image->storage_disk);
            $paths = [$image->storage_path, $image->preview_path, $image->thumbnail_path];
            foreach ($paths as $path) {
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    return false;
                }
            }
            $image->forceDelete();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
