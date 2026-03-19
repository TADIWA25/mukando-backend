<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;

class ListGroups extends Command
{
    protected $signature = 'mukando:list-groups
                            {--phone= : Filter by user phone number}
                            {--status= : Filter by group status (active, completed, cancelled)}';

    protected $description = 'List all savings groups, optionally filtered by user or status';

    public function handle(): int
    {
        $query = Group::with('creator');

        $phone = $this->option('phone');

        if ($phone) {
            $user = User::where('phone', $phone)->first();

            if (! $user) {
                $this->error("No user found with phone number: {$phone}");

                return self::FAILURE;
            }

            $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
        }

        $status = $this->option('status');

        if ($status) {
            $query->where('status', $status);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('No groups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Type', 'Frequency', 'Target', 'Contribution', 'Status', 'Invite Code', 'Created By'],
            $groups->map(fn ($g) => [
                $g->id,
                $g->name,
                $g->type,
                $g->frequency,
                number_format($g->target_amount, 2),
                number_format($g->contribution_amount, 2),
                $g->status,
                $g->invite_code,
                $g->creator?->name ?? 'N/A',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
