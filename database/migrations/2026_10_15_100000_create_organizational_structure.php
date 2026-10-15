<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 30)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('storage_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->text('location_details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('storage_area_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_area_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestamps();
        });
        // Supported by PostgreSQL and SQLite (isolated tests). History is not unique.
        DB::statement('CREATE UNIQUE INDEX assignments_active_area_unique ON storage_area_assignments (storage_area_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_active_user_unique ON storage_area_assignments (user_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_area_assignments');
        Schema::dropIfExists('storage_areas');
        Schema::dropIfExists('cities');
    }
};
