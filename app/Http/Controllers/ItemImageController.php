<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemImage;
use App\Services\InventoryImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemImageController extends Controller
{
    public function store(Request $request, InventoryImages $images, string $kind, int $record): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('inventory.image_max_kb')]]);
        $owner = $kind === 'assets' ? ItemAsset::findOrFail($record) : Item::findOrFail($record);
        $images->upload($owner, $request->file('image'), $request->user());

        return back();
    }

    public function update(Request $request, ItemImage $image): RedirectResponse
    {
        $data = $request->validate(['caption' => 'nullable|string|max:500', 'display_order' => 'required|integer|min:0|max:1000000', 'is_primary' => 'required|boolean']);
        DB::transaction(function () use ($image, $data) {
            $owner = $image->owner();
            $owner->newQuery()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $image->refresh();
            if ($data['is_primary']) {
                $owner->images()->where('is_primary', true)->update(['is_primary' => false]);
            }
            // A gallery always retains one primary image.
            elseif ($image->is_primary) {
                $data['is_primary'] = true;
            }
            $image->update($data);
        });

        return back();
    }

    public function destroy(ItemImage $image, InventoryImages $images): RedirectResponse
    {
        $images->remove($image);

        return back();
    }

    public function show(Request $request, ItemImage $image): StreamedResponse
    {
        Gate::authorize('view', $image->owner());
        $variant = $request->query('variant', 'preview');
        abort_unless(in_array($variant, ['original', 'preview', 'thumbnail'], true), 404);
        $path = match ($variant) {
            'original' => $image->storage_path,'thumbnail' => $image->thumbnail_path,default => $image->preview_path
        };
        $disk = Storage::disk($image->storage_disk);
        abort_unless($disk->exists($path), 404);
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $variant === 'original' ? $image->mime_type : 'image/webp',
            'Content-Disposition' => ($variant === 'original' ? 'attachment' : 'inline').'; filename="image-'.$image->id.'.'.($variant === 'original' ? pathinfo($path, PATHINFO_EXTENSION) : 'webp').'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
