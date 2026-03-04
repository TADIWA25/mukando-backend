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
        // Drop foreign key from group_members and recreate with cascade
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');
        });

        // Drop foreign key from contributions and recreate with cascade
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');
        });

        // Drop foreign key from loans and recreate with cascade
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');
        });

        // Drop foreign key from contribution_cycles and recreate with cascade
        Schema::table('contribution_cycles', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert group_members
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups');
        });

        // Revert contributions
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups');
        });

        // Revert loans
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups');
        });

        // Revert contribution_cycles
        Schema::table('contribution_cycles', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->foreign('group_id')
                ->references('id')
                ->on('groups');
        });
    }
};
