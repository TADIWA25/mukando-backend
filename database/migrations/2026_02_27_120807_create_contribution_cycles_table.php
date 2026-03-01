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
        if (Schema::hasTable('contribution_cycles')) {
            return;
        }

        Schema::create('contribution_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->integer('cycle_number');
            $table->date('due_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->unique(['group_id', 'cycle_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_cycles');
    }
};
