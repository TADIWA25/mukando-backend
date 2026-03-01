<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('groups')) {
            return;
        }

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['contribution', 'rounds', 'shared']);
            $table->decimal('target_amount', 10, 2);
            $table->decimal('contribution_amount', 10, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->string('invite_code', 6)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
