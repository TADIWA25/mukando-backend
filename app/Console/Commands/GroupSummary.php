<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;

class GroupSummary extends Command
{
    protected $signature = 'mukando:group-summary {id : The ID of the group}';

    protected $description = 'Display a summary report for a savings group';

    public function handle(): int
    {
        $group = Group::with(['members.user', 'contributions', 'loans'])->find($this->argument('id'));

        if (! $group) {
            $this->error("Group with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        $this->info("=== Group Summary: {$group->name} ===");
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $group->id],
                ['Name', $group->name],
                ['Type', $group->type],
                ['Frequency', $group->frequency],
                ['Target Amount', number_format($group->target_amount, 2)],
                ['Contribution Amount', number_format($group->contribution_amount, 2)],
                ['Status', $group->status],
                ['Invite Code', $group->invite_code],
                ['Created At', $group->created_at->toDateTimeString()],
            ]
        );

        $this->newLine();
        $this->info('--- Members ---');

        $this->table(
            ['ID', 'Name', 'Phone', 'Role', 'Joined At'],
            $group->members->map(fn ($m) => [
                $m->user->id,
                $m->user->name,
                $m->user->phone,
                $m->role,
                $m->created_at->toDateString(),
            ])->toArray()
        );

        $this->newLine();
        $this->info('--- Contributions ---');

        $totalPaid = $group->contributions->where('status', 'paid')->sum('amount_paid');
        $pending = $group->contributions->where('status', 'pending')->count();
        $paid = $group->contributions->where('status', 'paid')->count();

        $this->table(
            ['Field', 'Value'],
            [
                ['Total Paid', number_format($totalPaid, 2)],
                ['Paid Contributions', $paid],
                ['Pending Contributions', $pending],
            ]
        );

        $this->newLine();
        $this->info('--- Loans ---');

        $activeLoans = $group->loans->whereIn('status', ['pending', 'approved']);
        $totalLoaned = $group->loans->sum('amount');

        $this->table(
            ['Field', 'Value'],
            [
                ['Total Loans Issued', number_format($totalLoaned, 2)],
                ['Active Loans', $activeLoans->count()],
                ['Total Loans', $group->loans->count()],
            ]
        );

        return self::SUCCESS;
    }
}
