<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contributions') || ! Schema::hasColumn('contributions', 'cycle_id')) {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_cycle_unique'"))->isNotEmpty();

        if ($indexExists) {
            return;
        }

        $duplicates = DB::table('contributions')
            ->selectRaw("
                MAX(id) as keep_id,
                group_id,
                user_id,
                cycle_id,
                MAX(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as has_paid,
                MAX(amount_paid) as amount_paid,
                MAX(paid_at) as paid_at,
                MAX(marked_by) as marked_by
            ")
            ->groupBy('group_id', 'user_id', 'cycle_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('contributions')
                ->where('id', $duplicate->keep_id)
                ->update([
                    'status' => $duplicate->has_paid ? 'paid' : 'pending',
                    'amount_paid' => $duplicate->amount_paid,
                    'paid_at' => $duplicate->paid_at,
                    'marked_by' => $duplicate->marked_by,
                ]);

            DB::table('contributions')
                ->where('group_id', $duplicate->group_id)
                ->where('user_id', $duplicate->user_id)
                ->where('cycle_id', $duplicate->cycle_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->unique(['group_id', 'user_id', 'cycle_id'], 'contributions_group_user_cycle_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('contributions')) {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_cycle_unique'"))->isNotEmpty();

        if (! $indexExists) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropUnique('contributions_group_user_cycle_unique');
        });
    }
};
