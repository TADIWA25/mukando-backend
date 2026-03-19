<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\ContributionCycle;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RecordContribution extends Command
{
    protected $signature = 'mukando:record-contribution
                            {--group-id= : The ID of the group}
                            {--phone= : The phone number of the contributing member}
                            {--amount= : The amount being contributed}';

    protected $description = 'Record a contribution for a group member';

    public function handle(): int
    {
        $groupId = $this->option('group-id') ?? $this->ask('Group ID');
        $phone = $this->option('phone') ?? $this->ask('Member phone number');
        $amount = $this->option('amount') ?? $this->ask('Amount');

        $group = Group::find($groupId);

        if (! $group) {
            $this->error("Group with ID {$groupId} not found.");

            return self::FAILURE;
        }

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            $this->error("No user found with phone number: {$phone}");

            return self::FAILURE;
        }

        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            $this->error("{$user->name} is not a member of group '{$group->name}'.");

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['amount' => $amount],
            ['amount' => ['required', 'numeric', 'min:0.01']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Find or create the current contribution cycle
        $cycle = ContributionCycle::firstOrCreate(
            [
                'group_id' => $group->id,
                'due_date' => $group->currentPeriodEnd()->toDateString(),
            ],
            [
                'cycle_number' => ContributionCycle::where('group_id', $group->id)->count() + 1,
                'status' => 'open',
            ]
        );

        $contribution = Contribution::create([
            'group_id' => $group->id,
            'cycle_id' => $cycle->id,
            'user_id' => $user->id,
            'amount_paid' => $amount,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        $this->info('Contribution recorded successfully.');
        $this->table(
            ['ID', 'Group', 'Member', 'Amount', 'Cycle', 'Paid At'],
            [[
                $contribution->id,
                $group->name,
                $user->name,
                number_format($contribution->amount_paid, 2),
                $cycle->cycle_number,
                $contribution->paid_at->toDateTimeString(),
            ]]
        );

        return self::SUCCESS;
    }
}
