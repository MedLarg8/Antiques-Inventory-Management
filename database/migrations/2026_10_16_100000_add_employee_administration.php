<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->string('phone', 40)->nullable();
            $table->text('administrative_notes')->nullable();
            $table->unsignedBigInteger('auth_version')->default(0);
            $table->timestampTz('last_login_at')->nullable();
        });
        Schema::create('employee_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->json('details');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['employee_id', 'id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_audits');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'phone', 'administrative_notes', 'auth_version', 'last_login_at']);
        });
    }
};
