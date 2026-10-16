<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();
            $t->string('request_number', 40)->unique();
            $t->enum('operation_type', ['entry', 'exit']);
            $t->foreignId('storage_area_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'completed', 'cancelled'])->default('draft');
            $t->text('reason');
            $t->string('source_or_destination', 500)->nullable();
            $t->timestampTz('submitted_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('executed_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'submitted_at']);
            $t->index(['storage_area_id', 'status']);
            $t->index(['requested_by', 'created_at']);
            $t->index('reviewed_by');
            $t->index('executed_by');
            $t->index('created_at');
        });
        Schema::create('stock_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 18, 3);
            $t->string('item_reference_snapshot', 60);
            $t->string('item_name_snapshot', 200);
            $t->string('unit_snapshot', 30);
            $t->timestampsTz();
            $t->unique(['stock_request_id', 'item_id']);
            $t->index('item_id');
            // Supports a composite ledger FK tying each movement to the actual line/item/request.
            $t->unique(['id', 'stock_request_id', 'item_id'], 'stock_line_identity');
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_request_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('stock_request_item_id')->unique();
            $t->foreignId('storage_area_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->enum('movement_type', ['entry', 'exit']);
            $t->decimal('quantity_delta', 18, 3);
            $t->decimal('quantity_before', 18, 3);
            $t->decimal('quantity_after', 18, 3);
            $t->foreignId('executed_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('executed_at');
            $t->timestampTz('created_at');
            $t->foreign(['stock_request_item_id', 'stock_request_id', 'item_id'], 'movement_line_identity')->references(['id', 'stock_request_id', 'item_id'])->on('stock_request_items')->restrictOnDelete();
            $t->index(['storage_area_id', 'executed_at']);
            $t->index('stock_request_id');
            $t->index('item_id');
            $t->index('executed_by');
        });
        Schema::create('stock_request_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->enum('event_type', ['draft_created', 'draft_updated', 'submitted', 'approved', 'rejected', 'executed', 'cancelled']);
            $t->enum('previous_status', ['draft', 'pending_approval', 'approved', 'rejected', 'completed', 'cancelled'])->nullable();
            $t->enum('new_status', ['draft', 'pending_approval', 'approved', 'rejected', 'completed', 'cancelled']);
            $t->text('comment')->nullable();
            $t->timestampTz('created_at');
            $t->index(['stock_request_id', 'id']);
            $t->index('actor_id');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_request_items ADD CONSTRAINT positive_requested_quantity CHECK (quantity > 0)');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT valid_stock_movement CHECK (quantity_before >= 0 AND quantity_after >= 0 AND quantity_after = quantity_before + quantity_delta AND ((movement_type = 'entry' AND quantity_delta > 0) OR (movement_type = 'exit' AND quantity_delta < 0)))");
            DB::statement("ALTER TABLE stock_requests ADD CONSTRAINT stock_request_lifecycle CHECK (
    (status IN ('draft','cancelled') AND submitted_at IS NULL AND reviewed_by IS NULL AND reviewed_at IS NULL AND rejection_reason IS NULL AND executed_by IS NULL AND executed_at IS NULL)
    OR (status = 'pending_approval' AND submitted_at IS NOT NULL AND reviewed_by IS NULL AND reviewed_at IS NULL AND rejection_reason IS NULL AND executed_by IS NULL AND executed_at IS NULL)
    OR (status = 'approved' AND submitted_at IS NOT NULL AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND rejection_reason IS NULL AND executed_by IS NULL AND executed_at IS NULL)
    OR (status = 'rejected' AND submitted_at IS NOT NULL AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND rejection_reason IS NOT NULL AND length(trim(rejection_reason)) > 0 AND executed_by IS NULL AND executed_at IS NULL)
    OR (status = 'completed' AND submitted_at IS NOT NULL AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND rejection_reason IS NULL AND executed_by IS NOT NULL AND executed_at IS NOT NULL)
   )");
            DB::statement('ALTER TABLE stock_requests ADD CONSTRAINT independent_reviewer CHECK (reviewed_by IS NULL OR reviewed_by <> requested_by)');
        } else {
            DB::statement("CREATE TRIGGER positive_requested_quantity_insert BEFORE INSERT ON stock_request_items WHEN NEW.quantity <= 0 BEGIN SELECT RAISE(ABORT,'positive_requested_quantity'); END");
            DB::statement("CREATE TRIGGER positive_requested_quantity_update BEFORE UPDATE ON stock_request_items WHEN NEW.quantity <= 0 BEGIN SELECT RAISE(ABORT,'positive_requested_quantity'); END");
            DB::statement("CREATE TRIGGER valid_stock_movement BEFORE INSERT ON stock_movements WHEN NEW.quantity_before < 0 OR NEW.quantity_after < 0 OR abs(NEW.quantity_after - NEW.quantity_before - NEW.quantity_delta) > 0.00001 OR (NEW.movement_type = 'entry' AND NEW.quantity_delta <= 0) OR (NEW.movement_type = 'exit' AND NEW.quantity_delta >= 0) BEGIN SELECT RAISE(ABORT,'valid_stock_movement'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_events');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
