<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Overdue;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdueContributions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-overdue-contributions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process groups whose periods have ended and mark non-payers as overdue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process overdue contributions...');

        // Find all active groups
        $groups = Group::where('status', 'active')->get();

        foreach ($groups as $group) {
            $periodEnd = $group->currentPeriodEnd();

            // If the current period has ended (today is after period end)
            if (Carbon::today()->gt($periodEnd)) {
                $this->info("Processing ended period for group: {$group->name}");

                DB::transaction(function () use ($group, $periodEnd) {
                    $members = $group->members;

                    foreach ($members as $member) {
                        // Check if they paid in the period that just ended
                        $contribution = Contribution::where('group_id', $group->id)
                            ->where('user_id', $member->user_id)
                            ->first();

                        if (! $contribution || $contribution->status !== 'paid') {
                            $this->warn("Member {$member->user_id} has not paid. Adding to overdues.");

                            Overdue::updateOrCreate(
                                [
                                    'group_id' => $group->id,
                                    'user_id' => $member->user_id,
                                    'due_date' => $periodEnd->toDateString(),
                                ],
                                [
                                    'amount' => $group->contribution_amount,
                                    'status' => 'pending',
                                ]
                            );
                        }
                    }

                    // Reset contributions for the next period
                    Contribution::where('group_id', $group->id)->update([
                        'status' => 'pending',
                        'amount_paid' => 0,
                        'paid_at' => null,
                    ]);

                    // Reset GroupMember has_paid status
                    GroupMember::where('group_id', $group->id)->update([
                        'has_paid' => false,
                    ]);
                });

                $this->info("Contributions reset for group {$group->name} for the new period.");
            }
        }

        $this->info('Overdue processing completed.');
    }
}
