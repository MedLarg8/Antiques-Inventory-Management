<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemImage;
use App\Models\User;
use App\Services\InventoryImages;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class InventoryImageFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_database_save_removes_uploaded_files(): void
    {
        Storage::fake('local');
        $owner = new Item;
        $owner->id = 999999;
        try {
            app(InventoryImages::class)->upload($owner, UploadedFile::fake()->image('test.png'), User::factory()->create());
            $this->fail('Missing owner should fail.');
        } catch (ValidationException) {
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        $this->assertDatabaseCount('item_images', 0);
    }

    public function test_partial_write_failure_is_compensated(): void
    {
        $real = Storage::fake('local');
        $disk = Mockery::mock($real);
        $writes = 0;
        $disk->shouldReceive('put')->twice()->andReturnUsing(function ($path, $contents, $options) use ($real, &$writes) {
            return ++$writes === 1 ? $real->put($path, $contents, $options) : false;
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn ($paths) => $real->delete($paths));
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(InventoryImages::class)->upload(Item::factory()->create(), UploadedFile::fake()->image('test.png'), User::factory()->create());
            $this->fail('Write failure should fail upload.');
        } catch (ValidationException) {
            $this->assertSame([], $real->allFiles());
        }
        $this->assertDatabaseCount('item_images', 0);
    }

    public function test_failed_removal_retains_inaccessible_metadata_for_cleanup_retry(): void
    {
        $real = Storage::fake('local');
        $item = Item::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->post('/catalog/items/'.$item->id.'/images', ['image' => UploadedFile::fake()->image('test.png')])->assertSessionHasNoErrors();
        $image = ItemImage::sole();
        $disk = Mockery::mock($real);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk, $real);
        app(InventoryImages::class)->remove($image);
        $this->assertSoftDeleted($image);
        $this->get('/item-images/'.$image->id)->assertNotFound();
        $this->artisan('inventory:prune-images')->assertSuccessful();
        $this->assertDatabaseCount('item_images', 0);
        $this->assertSame([], $real->allFiles());
    }

    public function test_image_owner_and_primary_constraints_are_enforced(): void
    {
        Storage::fake('local');
        $item = Item::factory()->create();
        $asset = ItemAsset::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->post('/catalog/items/'.$item->id.'/images', ['image' => UploadedFile::fake()->image('test.png')])->assertSessionHasNoErrors();
        $image = ItemImage::sole();
        foreach ([
            fn () => DB::table('item_images')->where('id', $image->id)->update(['item_asset_id' => $asset->id]),
            fn () => DB::table('item_images')->where('id', $image->id)->update(['item_id' => null]),
            fn () => ItemImage::create([...$image->getAttributes(), 'id' => $image->id + 1]),
            fn () => DB::table('items')->where('id', $item->id)->delete(),
        ] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('Invalid image relationship accepted.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }
}
