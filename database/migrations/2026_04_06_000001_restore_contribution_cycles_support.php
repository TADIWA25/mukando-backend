<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('groups') && ! Schema::hasColumn('groups', 'start_date')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('frequency');
            });
        }

        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'start_date')) {
            DB::table('groups')
                ->whereNull('start_date')
                ->update([
                    'start_date' => DB::raw('DATE(created_at)'),
                ]);
        }

        if (! Schema::hasTable('contribution_cycles')) {
            Schema::create('contribution_cycles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->unsignedInteger('cycle_number');
                $table->date('due_date');
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamps();

                $table->unique(['group_id', 'cycle_number'], 'contribution_cycles_group_cycle_unique');
            });
        }

        if (Schema::hasTable('contributions') && ! Schema::hasColumn('contributions', 'cycle_id')) {
            Schema::table('contributions', function (Blueprint $table) {
                $table->foreignId('cycle_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained('contribution_cycles')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('contributions')) {
            return;
        }

        $groupIdIndexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_id_index'"))->isNotEmpty();
        $userIdIndexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_user_id_index'"))->isNotEmpty();
        $groupUserUniqueExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_unique'"))->isNotEmpty();
        $groupUserCycleUniqueExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_cycle_unique'"))->isNotEmpty();

        Schema::table('contributions', function (Blueprint $table) use ($groupIdIndexExists, $userIdIndexExists, $groupUserUniqueExists, $groupUserCycleUniqueExists) {
            if (! $groupIdIndexExists) {
                $table->index('group_id', 'contributions_group_id_index');
            }

            if (! $userIdIndexExists) {
                $table->index('user_id', 'contributions_user_id_index');
            }

            if ($groupUserUniqueExists) {
                $table->dropUnique('contributions_group_user_unique');
            }

            if (! $groupUserCycleUniqueExists) {
                $table->unique(['group_id', 'user_id', 'cycle_id'], 'contributions_group_user_cycle_unique');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('contributions')) {
            $groupIdIndexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_id_index'"))->isNotEmpty();
            $userIdIndexExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_user_id_index'"))->isNotEmpty();
            $groupUserCycleUniqueExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_cycle_unique'"))->isNotEmpty();
            $groupUserUniqueExists = collect(DB::select("SHOW INDEX FROM contributions WHERE Key_name = 'contributions_group_user_unique'"))->isNotEmpty();

            Schema::table('contributions', function (Blueprint $table) use ($groupIdIndexExists, $userIdIndexExists, $groupUserCycleUniqueExists, $groupUserUniqueExists) {
                if ($groupUserCycleUniqueExists) {
                    $table->dropUnique('contributions_group_user_cycle_unique');
                }

                if (! $groupUserUniqueExists) {
                    $table->unique(['group_id', 'user_id'], 'contributions_group_user_unique');
                }

                if ($groupIdIndexExists) {
                    $table->dropIndex('contributions_group_id_index');
                }

                if ($userIdIndexExists) {
                    $table->dropIndex('contributions_user_id_index');
                }

                if (Schema::hasColumn('contributions', 'cycle_id')) {
                    $table->dropConstrainedForeignId('cycle_id');
                }
            });
        }

        Schema::dropIfExists('contribution_cycles');

        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'start_date')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropColumn('start_date');
            });
        }
    }
};
