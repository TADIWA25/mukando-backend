<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contribution;
use App\Models\GroupMember;
use Carbon\Carbon;

class NotifyOverdueContributions extends Command
{
    protected $signature = 'notify:overdue-contributions';
    protected $description = 'Notify members with overdue contributions';

    public function handle()
    {
        $now = Carbon::now();

        // Get all group members
        $members = GroupMember::with('user','group')->get();

        foreach ($members as $member) {
            $group = $member->group;

            // Check last contribution
            $lastContribution = Contribution::where('user_id', $member->user_id)
                ->where('group_id', $group->id)
                ->latest('paid_at')
                ->first();

            $frequencyDays = match ($group->frequency) {
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
                default => 30
            };

            $dueDate = $lastContribution ? $lastContribution->paid_at->addDays($frequencyDays) : $member->created_at->addDays($frequencyDays);

            if ($now->gt($dueDate)) {
                // Send SMS notification (placeholder)
                // SmsService::send($member->user->phone, "Your contribution to {$group->name} is overdue.");
                $this->info("Overdue notification for {$member->user->name} in group {$group->name}");
            }
        }
    }
}
