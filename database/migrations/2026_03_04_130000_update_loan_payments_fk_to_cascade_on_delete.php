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
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->foreign('loan_id')
                ->references('id')
                ->on('loans')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->foreign('loan_id')
                ->references('id')
                ->on('loans');
        });
    }
};

