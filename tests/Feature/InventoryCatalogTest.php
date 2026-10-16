<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TrackingMode;
use App\Models\Item;
use App\Models\ItemAsset;
use App\Models\ItemCategory;
use App\Models\ItemImage;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\StorageAreaInventory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    private function payload(Item $item): array
    {
        return [...$item->only(['name', 'reference_code', 'category_id', 'unit_of_measure', 'is_active']), 'tracking_mode' => $item->tracking_mode->value];
    }

    public function test_category_management_rejects_duplicates_and_cycles(): void
    {
        $this->actingAs($this->admin())->post('/catalog/categories', ['name' => 'Tools', 'code' => 'TOOLS', 'is_active' => true])->assertSessionHasNoErrors();
        $root = ItemCategory::sole();
        $child = ItemCategory::factory()->create(['parent_id' => $root->id]);
        $this->post('/catalog/categories', ['name' => 'Duplicate', 'code' => 'TOOLS', 'is_active' => true])->assertSessionHasErrors('code');
        foreach ([$root->id, $child->id] as $parent) {
            $this->put('/catalog/categories/'.$root->id, ['name' => 'Tools', 'code' => 'TOOLS', 'is_active' => false, 'parent_id' => $parent])->assertSessionHasErrors('parent_id');
        }
        $this->put('/catalog/categories/'.$root->id, ['name' => 'Tools', 'code' => 'TOOLS', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($root->fresh()->is_active);
        $this->get('/categories?search=Tools')->assertInertia(fn (Assert $p) => $p->component('inventory/categories')->has('categories.data', 1));
    }

    public function test_items_validate_metadata_and_preserve_existing_balances(): void
    {
        $item = Item::factory()->create();
        $data = $this->payload($item);
        $this->actingAs($this->admin())->post('/catalog/items', $data)->assertSessionHasErrors('reference_code');
        $this->post('/catalog/items', [...$data, 'reference_code' => 'NEW', 'category_id' => 999999, 'tracking_mode' => 'invalid'])->assertSessionHasErrors(['category_id', 'tracking_mode']);
        $this->post('/catalog/items', [...$data, 'reference_code' => 'NEW'])->assertSessionHasNoErrors();
        $balance = StorageAreaInventory::factory()->create(['item_id' => $item->id]);
        $this->put('/catalog/items/'.$item->id, [...$data, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_active);
        $this->assertNotNull($balance->fresh());
        $this->put('/catalog/items/'.$item->id, [...$data, 'tracking_mode' => 'individual_asset'])->assertSessionHasErrors('tracking_mode');
        $this->put('/catalog/items/'.$item->id, [...$data, 'unit_of_measure' => 'liter'])->assertSessionHasErrors('unit_of_measure');
        $this->get('/inventory?status=inactive')->assertInertia(fn (Assert $p) => $p->component('inventory/index')->has('items.data', 1));
    }

    public function test_assets_are_descriptive_and_cannot_acquire_locations_or_quantities(): void
    {
        $item = Item::factory()->create(['tracking_mode' => TrackingMode::Individual]);
        $this->actingAs($this->admin())->post('/catalog/assets?item='.$item->id, ['asset_reference' => 'ANT-1', 'serial_number' => 'SERIAL'])->assertSessionHasNoErrors();
        $asset = ItemAsset::sole();
        $this->post('/catalog/assets?item='.$item->id, ['asset_reference' => 'ANT-1'])->assertSessionHasErrors('asset_reference');
        $quantity = Item::factory()->create();
        $this->post('/catalog/assets?item='.$quantity->id, ['asset_reference' => 'BAD'])->assertSessionHasErrors('asset_reference');
        $this->put('/catalog/assets/'.$asset->id, ['asset_reference' => 'ANT-1', 'quantity' => 5, 'storage_area_id' => 1, 'item_id' => $quantity->id])->assertSessionHasErrors(['quantity', 'storage_area_id', 'item_id']);
        $this->put('/catalog/assets/'.$asset->id, ['asset_reference' => 'ANT-1', 'condition_notes' => 'Scratched'])->assertSessionHasNoErrors();
        $this->put('/catalog/items/'.$item->id, [...$this->payload($item), 'tracking_mode' => 'quantity_reusable'])->assertSessionHasErrors('tracking_mode');
        $this->assertDatabaseCount('storage_area_inventory', 0);
        foreach (['/catalog/assets/create?item='.$item->id, '/catalog/assets/'.$asset->id.'/edit', '/assets/'.$asset->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_threshold_configuration_never_initializes_or_changes_balances(): void
    {
        $item = Item::factory()->create();
        $area = StorageArea::factory()->create();
        $this->actingAs($this->admin())->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => '10.125'])->assertSessionHasNoErrors();
        $b = StorageAreaInventory::sole();
        $this->assertSame('0.000', $b->quantity);
        $this->assertNull($b->initialized_at);
        $this->assertNull($b->toView()['quantity']);
        $this->assertSame(0, StorageAreaInventory::lowStock()->count());
        foreach (['-1', '1.1234', '1e3', '1000000000000000'] as $v) {
            $this->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => $v])->assertSessionHasErrors('minimum_quantity');
        }
        $this->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => '2', 'quantity' => 50, 'initialized_at' => now()])->assertSessionHasErrors(['quantity', 'initialized_at']);
        $b->forceFill(['quantity' => '4.125', 'initialized_at' => now()])->save();
        $this->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => '4.125'])->assertSessionHasNoErrors();
        $this->assertSame('4.125', $b->fresh()->quantity);
        $this->assertSame(0, StorageAreaInventory::lowStock()->count());
        $this->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => '4.126'])->assertSessionHasNoErrors();
        $this->assertSame(1, StorageAreaInventory::lowStock()->count());
        $this->put('/inventory/'.$item->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => null])->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->minimum_quantity);
        $this->assertDatabaseCount('storage_area_inventory', 1);
        $assetItem = Item::factory()->create(['tracking_mode' => TrackingMode::Individual]);
        $this->put('/inventory/'.$assetItem->id.'/threshold', ['storage_area_id' => $area->id, 'minimum_quantity' => '1'])->assertSessionHasErrors('minimum_quantity');
        $this->put('/inventory/'.$item->id, ['quantity' => 99])->assertMethodNotAllowed();
    }

    public function test_employee_visibility_is_scoped_and_all_management_is_denied(): void
    {
        $assignment = StorageAreaAssignment::factory()->create();
        $other = StorageAreaInventory::factory()->create();
        $own = StorageAreaInventory::factory()->create(['storage_area_id' => $assignment->storage_area_id]);
        $asset = ItemAsset::factory()->create();
        $this->actingAs($assignment->user)->get('/inventory')->assertInertia(fn (Assert $p) => $p->component('inventory/index')->has('items.data', 1)->where('items.data.0.id', $own->item_id));
        $this->get('/inventory/'.$own->item_id)->assertOk()->assertInertia(fn (Assert $p) => $p->has('balances.data', 1)->has('areas', 0)->where('assets', null));
        $this->get('/inventory/'.$other->item_id)->assertForbidden();
        $this->get('/storage-areas/'.$other->storage_area_id.'/inventory')->assertForbidden();
        $this->get('/assets/'.$asset->id)->assertForbidden();
        foreach (['items', 'assets', 'categories'] as $kind) {
            $this->get('/catalog/'.$kind.'/create')->assertForbidden();
            $this->post('/catalog/'.$kind, [])->assertForbidden();
            $this->put('/catalog/'.$kind.'/1', [])->assertForbidden();
        }
        $this->put('/inventory/'.$own->item_id.'/threshold', [])->assertForbidden();
        $this->get('/categories')->assertOk();
        $this->actingAs(User::factory()->create())->get('/inventory')->assertInertia(fn (Assert $p) => $p->has('items.data', 0));
        $this->get('/inventory/'.$own->item_id)->assertForbidden();
    }

    public function test_database_enforces_unique_balance_and_nonnegative_values(): void
    {
        $b = StorageAreaInventory::factory()->create();
        foreach ([
            fn () => StorageAreaInventory::factory()->create(['item_id' => $b->item_id, 'storage_area_id' => $b->storage_area_id]),
            fn () => DB::table('storage_area_inventory')->where('id', $b->id)->update(['quantity' => -1]),
            fn () => DB::table('storage_area_inventory')->where('id', $b->id)->update(['minimum_quantity' => -1]),
            fn () => DB::table('storage_area_inventory')->where('id', $b->id)->update(['quantity' => 1]),
        ] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('Constraint must reject invalid data.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_private_images_upload_optimize_authorize_reorder_and_cleanup(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $item = Item::factory()->create();
        $this->actingAs($admin)->post('/catalog/items/'.$item->id.'/images', ['image' => UploadedFile::fake()->image('unsafe name.png', 800, 600)])->assertSessionHasNoErrors();
        $first = ItemImage::sole();
        Storage::disk('local')->assertExists([$first->storage_path, $first->preview_path, $first->thumbnail_path]);
        $this->assertTrue($first->is_primary);
        $this->assertStringNotContainsString('unsafe', $first->storage_path);
        $this->get('/item-images/'.$first->id)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post('/catalog/items/'.$item->id.'/images', ['image' => UploadedFile::fake()->image('second.jpg')])->assertSessionHasNoErrors();
        $second = ItemImage::latest('id')->first();
        $this->patch('/item-images/'.$second->id, ['caption' => 'Front view', 'display_order' => 0, 'is_primary' => true])->assertSessionHasNoErrors();
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertSame($second->id, $item->images()->first()->id);
        $this->actingAs(User::factory()->create())->get('/item-images/'.$first->id)->assertForbidden();
        $assignment = StorageAreaAssignment::factory()->create();
        StorageAreaInventory::factory()->create(['item_id' => $item->id, 'storage_area_id' => $assignment->storage_area_id]);
        $this->actingAs($assignment->user)->get('/item-images/'.$first->id.'?variant=original')->assertOk();
        $this->post('/catalog/items/'.$item->id.'/images', [])->assertForbidden();
        $this->delete('/item-images/'.$first->id)->assertForbidden();
        $this->actingAs($admin)->delete('/item-images/'.$second->id)->assertRedirect();
        Storage::disk('local')->assertMissing([$second->storage_path, $second->preview_path, $second->thumbnail_path]);
        $this->assertTrue($first->fresh()->is_primary);
        Storage::disk('local')->delete($first->preview_path);
        $this->get('/item-images/'.$first->id)->assertNotFound();
        $this->delete('/item-images/'.$first->id)->assertRedirect();
        $this->assertDatabaseCount('item_images', 0);
    }

    public function test_invalid_and_oversized_images_are_rejected_without_files(): void
    {
        Storage::fake('local');
        $item = Item::factory()->create();
        $this->actingAs($this->admin());
        foreach ([UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('fake.png', 1, 'image/png'), UploadedFile::fake()->image('large.jpg')->size(10241)] as $file) {
            $this->post('/catalog/items/'.$item->id.'/images', ['image' => $file])->assertSessionHasErrors('image');
        }
        config(['inventory.image_max_pixels' => 10]);
        $this->post('/catalog/items/'.$item->id.'/images', ['image' => UploadedFile::fake()->image('wide.jpg', 20, 20)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('item_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_asset_images_are_admin_only_and_upload_history_protects_account(): void
    {
        Storage::fake('local');
        $asset = ItemAsset::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/catalog/assets/'.$asset->id.'/images', ['image' => UploadedFile::fake()->image('antique.png')])->assertSessionHasNoErrors();
        $image = ItemImage::sole();
        $this->assertNull($image->item_id);
        $this->assertSame($asset->id, $image->item_asset_id);
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->actingAs(User::factory()->create())->get('/item-images/'.$image->id)->assertForbidden();
    }

    public function test_catalog_pages_and_dashboard_use_real_counts(): void
    {
        $item = Item::factory()->create();
        ItemAsset::factory()->create();
        StorageAreaInventory::factory()->create(['item_id' => $item->id, 'quantity' => '1.000', 'minimum_quantity' => '2.000', 'initialized_at' => now()]);
        StorageAreaInventory::factory()->create();
        $this->actingAs($this->admin())->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('inventory.Low-stock balances', 1)->where('inventory.Uninitialized balance records', 1));
        foreach (['/catalog/items/create', '/catalog/items/'.$item->id.'/edit', '/catalog/categories/create', '/catalog/categories/'.$item->category_id.'/edit', '/inventory/'.$item->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/inventory?search='.$item->reference_code)->assertInertia(fn (Assert $p) => $p->has('items.data', 1));
    }
}
