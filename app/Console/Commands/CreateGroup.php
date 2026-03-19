<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateGroup extends Command
{
    protected $signature = 'mukando:create-group
                            {--name= : The group name}
                            {--type= : The group type (contribution, rounds, shared)}
                            {--frequency= : Contribution frequency (daily, weekly, monthly)}
                            {--target= : Target amount}
                            {--contribution= : Contribution amount per cycle}
                            {--admin-phone= : Phone number of the group admin}';

    protected $description = 'Create a new savings group';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Group name');

        $type = $this->option('type') ?? $this->choice(
            'Group type',
            ['contribution', 'rounds', 'shared'],
            0
        );

        $frequency = $this->option('frequency') ?? $this->choice(
            'Contribution frequency',
            ['daily', 'weekly', 'monthly'],
            2
        );

        $targetAmount = $this->option('target') ?? $this->ask('Target amount');
        $contributionAmount = $this->option('contribution') ?? $this->ask('Contribution amount per cycle');
        $adminPhone = $this->option('admin-phone') ?? $this->ask('Admin phone number');

        $admin = User::where('phone', $adminPhone)->first();

        if (! $admin) {
            $this->error("No user found with phone number: {$adminPhone}");

            return self::FAILURE;
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'type' => $type,
                'frequency' => $frequency,
                'target_amount' => $targetAmount,
                'contribution_amount' => $contributionAmount,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', 'in:contribution,rounds,shared'],
                'frequency' => ['required', 'in:daily,weekly,monthly'],
                'target_amount' => ['required', 'numeric', 'min:0'],
                'contribution_amount' => ['required', 'numeric', 'min:0'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $group = Group::create([
            'name' => $name,
            'type' => $type,
            'frequency' => $frequency,
            'target_amount' => $targetAmount,
            'contribution_amount' => $contributionAmount,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $this->info('Group created successfully.');
        $this->table(
            ['ID', 'Name', 'Type', 'Frequency', 'Target', 'Contribution', 'Invite Code'],
            [[
                $group->id,
                $group->name,
                $group->type,
                $group->frequency,
                $group->target_amount,
                $group->contribution_amount,
                $group->invite_code,
            ]]
        );

        return self::SUCCESS;
    }
}
