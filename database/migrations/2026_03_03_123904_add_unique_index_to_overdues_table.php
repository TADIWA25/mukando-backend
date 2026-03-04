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
        Schema::table('overdues', function (Blueprint $table) {
            $table->unique(['group_id', 'user_id', 'due_date'], 'overdues_group_user_due_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overdues', function (Blueprint $table) {
            $table->dropUnique('overdues_group_user_due_date_unique');
        });
    }
};
