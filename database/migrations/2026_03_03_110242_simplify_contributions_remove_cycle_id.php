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
        if (! Schema::hasTable('contributions')) {
            return;
        }

        $hasCycleId = Schema::hasColumn('contributions', 'cycle_id');
        $hasGroupUserCycleUnique = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_cycle_unique'"))->isNotEmpty();
        $hasCycleUserUnique = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_cycle_id_user_id_unique'"))->isNotEmpty();
        $hasGroupUserUnique = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_unique'"))->isNotEmpty();
        $databaseName = DB::getDatabaseName();
        $hasCycleIdForeignKey = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$databaseName, 'contributions', 'cycle_id']
        ))->isNotEmpty();
        $hasGroupIdForeignKey = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$databaseName, 'contributions', 'group_id']
        ))->isNotEmpty();
        $hasUserIdForeignKey = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$databaseName, 'contributions', 'user_id']
        ))->isNotEmpty();
        $duplicates = DB::table('contributions')
            ->selectRaw("
                MAX(id) as keep_id,
                group_id,
                user_id,
                MAX(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as has_paid,
                MAX(amount_paid) as amount_paid,
                MAX(paid_at) as paid_at,
                MAX(marked_by) as marked_by
            ")
            ->groupBy('group_id', 'user_id')
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
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('contributions', function (Blueprint $table) use ($hasCycleId, $hasCycleIdForeignKey, $hasGroupIdForeignKey, $hasUserIdForeignKey, $hasGroupUserCycleUnique, $hasCycleUserUnique, $hasGroupUserUnique) {
            if ($hasCycleId && $hasCycleIdForeignKey) {
                $table->dropForeign(['cycle_id']);
            }

            if ($hasGroupIdForeignKey) {
                $table->dropForeign(['group_id']);
            }

            if ($hasUserIdForeignKey) {
                $table->dropForeign(['user_id']);
            }

            if ($hasGroupUserCycleUnique) {
                $table->dropUnique('contributions_group_user_cycle_unique');
            }

            if ($hasCycleUserUnique) {
                $table->dropUnique('contributions_cycle_id_user_id_unique');
            }

            if ($hasCycleId) {
                $table->dropColumn('cycle_id');
            }

            if (! $hasGroupUserUnique) {
                $table->unique(['group_id', 'user_id'], 'contributions_group_user_unique');
            }

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
