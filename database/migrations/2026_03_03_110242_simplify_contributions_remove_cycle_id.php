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
        Schema::table('contributions', function (Blueprint $table) {
            // Drop foreign keys first to allow index dropping
            $table->dropForeign(['cycle_id']);
            $table->dropForeign(['group_id']);
            $table->dropForeign(['user_id']);
            
            // Now drop the unique indexes
            $table->dropUnique('contributions_group_user_cycle_unique');
            $table->dropUnique('contributions_cycle_id_user_id_unique');
            
            // Drop the cycle_id column
            $table->dropColumn('cycle_id');
            
            // Add unique index for group_id and user_id
            $table->unique(['group_id', 'user_id'], 'contributions_group_user_unique');
            
            // Re-add foreign keys for group_id and user_id
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('overdues', function (Blueprint $table) {
            $table->dropForeign(['cycle_id']);
            $table->dropColumn('cycle_id');
            $table->date('due_date')->nullable()->after('amount');
        });

        Schema::dropIfExists('contribution_cycles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('contribution_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->integer('cycle_number');
            $table->date('due_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::table('overdues', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->after('user_id')->constrained('contribution_cycles')->cascadeOnDelete();
            $table->dropColumn('due_date');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropUnique('contributions_group_user_unique');
            $table->foreignId('cycle_id')->nullable()->after('group_id')->constrained('contribution_cycles')->cascadeOnDelete();
            $table->unique(['group_id', 'user_id', 'cycle_id'], 'contributions_group_user_cycle_unique');
        });
    }
};
