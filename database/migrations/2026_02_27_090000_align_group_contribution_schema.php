<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table) {
                if (!Schema::hasColumn('groups', 'target_amount')) {
                    $table->decimal('target_amount', 10, 2)->default(0)->after('type');
                }

                if (!Schema::hasColumn('groups', 'status')) {
                    $table->enum('status', ['active', 'completed', 'cancelled'])->default('active')->after('frequency');
                }
            });

            if (Schema::hasColumn('groups', 'interest_rate')) {
                Schema::table('groups', function (Blueprint $table) {
                    $table->dropColumn('interest_rate');
                });
            }

            if (Schema::hasColumn('groups', 'total_collected')) {
                Schema::table('groups', function (Blueprint $table) {
                    $table->dropColumn('total_collected');
                });
            }

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE groups MODIFY type ENUM('contribution','rounds','shared') NOT NULL");
                DB::statement("ALTER TABLE groups MODIFY target_amount DECIMAL(10,2) NOT NULL");
                DB::statement("ALTER TABLE groups MODIFY contribution_amount DECIMAL(10,2) NOT NULL");
                DB::statement("ALTER TABLE groups MODIFY frequency ENUM('daily','weekly','monthly') NOT NULL");
                DB::statement("ALTER TABLE groups MODIFY status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active'");
                DB::statement("ALTER TABLE groups MODIFY invite_code VARCHAR(6) NOT NULL");
            }
        }

        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table) {
                if (!Schema::hasColumn('group_members', 'role')) {
                    $table->enum('role', ['admin', 'member'])->default('member');
                }

                $table->unique(['group_id', 'user_id'], 'group_members_group_user_unique');
            });
        }

        if (!Schema::hasTable('contribution_cycles')) {
            Schema::create('contribution_cycles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->integer('cycle_number');
                $table->date('due_date');
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contributions')) {
            Schema::table('contributions', function (Blueprint $table) {
                if (!Schema::hasColumn('contributions', 'cycle_id')) {
                    $table->foreignId('cycle_id')->nullable()->after('group_id')->constrained('contribution_cycles')->cascadeOnDelete();
                }

                if (!Schema::hasColumn('contributions', 'amount_paid')) {
                    $table->decimal('amount_paid', 10, 2)->nullable()->after('user_id');
                }

                if (!Schema::hasColumn('contributions', 'status')) {
                    $table->enum('status', ['pending', 'paid'])->default('pending')->after('amount_paid');
                }

                if (!Schema::hasColumn('contributions', 'marked_by')) {
                    $table->foreignId('marked_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("UPDATE contributions SET amount_paid = amount WHERE amount_paid IS NULL");
                DB::statement("ALTER TABLE contributions MODIFY paid_at TIMESTAMP NULL");

                if (Schema::hasColumn('contributions', 'amount')) {
                    DB::statement("ALTER TABLE contributions DROP COLUMN amount");
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contributions')) {
            Schema::table('contributions', function (Blueprint $table) {
                if (Schema::hasColumn('contributions', 'marked_by')) {
                    $table->dropConstrainedForeignId('marked_by');
                }

                if (Schema::hasColumn('contributions', 'status')) {
                    $table->dropColumn('status');
                }

                if (Schema::hasColumn('contributions', 'amount_paid')) {
                    $table->dropColumn('amount_paid');
                }

                if (Schema::hasColumn('contributions', 'cycle_id')) {
                    $table->dropConstrainedForeignId('cycle_id');
                }
            });
        }

        Schema::dropIfExists('contribution_cycles');

        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropUnique('group_members_group_user_unique');
            });
        }
    }
};
