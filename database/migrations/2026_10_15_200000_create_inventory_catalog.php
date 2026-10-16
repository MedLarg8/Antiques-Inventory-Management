<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->index('parent_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 60)->unique();
            $table->string('name', 200)->index();
            $table->foreignId('category_id')->constrained('item_categories')->restrictOnDelete();
            $table->index('category_id');
            $table->enum('tracking_mode', ['quantity_consumable', 'quantity_reusable', 'individual_asset'])->index();
            $table->string('unit_of_measure', 30);
            $table->text('description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('item_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->index('item_id');
            $table->string('asset_reference', 60)->unique();
            $table->string('serial_number', 150)->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('storage_area_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_area_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('minimum_quantity', 18, 3)->nullable();
            $table->timestampTz('initialized_at')->nullable();
            $table->timestamps();
            $table->unique(['storage_area_id', 'item_id']);
            $table->index('item_id');
            $table->index('initialized_at');
        });
        Schema::create('item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('item_asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('storage_disk', 40);
            $table->string('storage_path');
            $table->string('thumbnail_path');
            $table->string('preview_path');
            $table->string('original_filename');
            $table->string('mime_type', 60);
            $table->unsignedBigInteger('file_size');
            $table->string('caption', 500)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['item_id', 'display_order']);
            $table->index(['item_asset_id', 'display_order']);
            $table->index('uploaded_by');
        });
        $this->constraint('storage_area_inventory', 'inventory_nonnegative', 'quantity >= 0 AND (minimum_quantity IS NULL OR minimum_quantity >= 0)');
        $this->constraint('storage_area_inventory', 'inventory_uninitialized_zero', 'initialized_at IS NOT NULL OR quantity = 0');
        $this->constraint('item_images', 'image_exactly_one_owner', '(item_id IS NOT NULL AND item_asset_id IS NULL) OR (item_id IS NULL AND item_asset_id IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX item_primary_image ON item_images (item_id) WHERE is_primary = true AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX asset_primary_image ON item_images (item_asset_id) WHERE is_primary = true AND deleted_at IS NULL');
    }

    private function constraint(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        } else {
            // SQLite cannot add CHECK constraints after table creation.
            $columns = ['quantity', 'minimum_quantity', 'initialized_at', 'item_id', 'item_asset_id'];
            $condition = preg_replace('/\\b('.implode('|', $columns).')\\b/', 'NEW.$1', $expression);
            foreach (['INSERT', 'UPDATE'] as $operation) {
                DB::statement("CREATE TRIGGER {$name}_{$operation} BEFORE {$operation} ON {$table} WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_images');
        Schema::dropIfExists('storage_area_inventory');
        Schema::dropIfExists('item_assets');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }
};
