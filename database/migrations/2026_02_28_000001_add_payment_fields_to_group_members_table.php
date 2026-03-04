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
        Schema::table('group_members', function (Blueprint $table) {
            $table->boolean('has_paid')->default(false)->after('role');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('has_paid');
            $table->date('payment_period_start')->nullable()->after('amount_paid');
            $table->dateTime('last_payment_at')->nullable()->after('payment_period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn([
                'has_paid',
                'amount_paid',
                'payment_period_start',
                'last_payment_at',
            ]);
        });
    }
};
