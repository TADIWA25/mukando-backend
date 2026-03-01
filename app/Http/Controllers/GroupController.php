<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $memberships = GroupMember::query()
            ->where('user_id', $userId)
            ->get(['group_id', 'role'])
            ->keyBy('group_id');

        $groups = Group::query()
            ->whereIn('id', $memberships->keys())
            ->latest()
            ->get();

        $data = $groups->map(function (Group $group) use ($memberships) {
            $membership = $memberships->get($group->id);
            $isAdmin = $membership?->role === 'admin';

            return [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'target_amount' => number_format((float) $group->target_amount, 2, '.', ''),
                'contribution_amount' => number_format((float) $group->contribution_amount, 2, '.', ''),
                'frequency' => $group->frequency,
                'status' => $group->status,
                'role' => $membership?->role,
                'invite_code' => $group->invite_code,
                'can_invite' => $isAdmin,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:contribution,rounds,shared'],
            'target_amount' => ['required_if:type,contribution', 'numeric', 'gt:0'],
            'contribution_amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'status' => ['sometimes', 'in:active,completed,cancelled'],
            'start_date' => ['required', 'date'],
        ]);

        $userId = $request->user()->id;

        $group = DB::transaction(function () use ($validated, $userId) {
            $targetAmount = $validated['target_amount'] ?? null;
            if ($validated['type'] === 'rounds' && $targetAmount === null) {
                $targetAmount = (float) $validated['contribution_amount'];
            }

            $group = Group::query()->create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'target_amount' => $targetAmount,
                'contribution_amount' => $validated['contribution_amount'],
                'frequency' => $validated['frequency'],
                'status' => $validated['status'] ?? 'active',
                'created_by' => $userId,
            ]);

            GroupMember::query()->create([
                'group_id' => $group->id,
                'user_id' => $userId,
                'role' => 'admin',
            ]);

            $cycleCount = (int) ceil($group->target_amount / $group->contribution_amount);
            $cycleCount = max(1, $cycleCount);

            $dueDate = Carbon::parse($validated['start_date'])->startOfDay();

            for ($cycleNumber = 1; $cycleNumber <= $cycleCount; $cycleNumber++) {
                $cycle = $group->cycles()->create([
                    'cycle_number' => $cycleNumber,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'open',
                ]);

                Contribution::query()->create([
                    'group_id' => $group->id,
                    'cycle_id' => $cycle->id,
                    'user_id' => $userId,
                    'status' => 'pending',
                ]);

                $dueDate = $this->advanceDateByFrequency($dueDate, $group->frequency);
            }

            return $group;
        });

        return response()->json([
            'status' => true,
            'data' => $group->fresh(),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $group = Group::query()->findOrFail($id);
        $userId = $request->user()->id;

        $membership = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if (! $membership) {
            abort(403, 'You are not a member of this group.');
        }

        $isAdmin = $membership->role === 'admin';

        $totalContributed = Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'paid')
            ->sum('amount_paid');

        $currentCycle = $group->cycles()
            ->where('status', 'open')
            ->orderBy('cycle_number')
            ->first();

        $members = GroupMember::query()
            ->with('user:id,name,phone')
            ->where('group_id', $group->id)
            ->get();

        $paidUserIds = collect();
        if ($currentCycle) {
            $paidUserIds = Contribution::query()
                ->where('group_id', $group->id)
                ->where('cycle_id', $currentCycle->id)
                ->where('status', 'paid')
                ->pluck('user_id');
        }

        $memberPayload = $members->map(function (GroupMember $member) use ($paidUserIds) {
            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'phone' => $member->user?->phone,
                'role' => $member->role,
                'joined_at' => optional($member->created_at)->toDateTimeString(),
                'paid_this_cycle' => $paidUserIds->contains($member->user_id),
            ];
        })->values();

        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'target_amount' => number_format((float) $group->target_amount, 2, '.', ''),
            'contribution_amount' => number_format((float) $group->contribution_amount, 2, '.', ''),
            'frequency' => $group->frequency,
            'status' => $group->status,
            'invite_code' => $isAdmin ? $group->invite_code : null,
            'total_contributed' => number_format((float) $totalContributed, 2, '.', ''),
            'current_cycle' => $currentCycle ? [
                'id' => $currentCycle->id,
                'cycle_number' => $currentCycle->cycle_number,
                'due_date' => $currentCycle->due_date,
                'status' => $currentCycle->status,
            ] : null,
            'members' => $memberPayload,
        ];

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    private function advanceDateByFrequency(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => $date->copy()->addDay(),
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonth(),
            default => $date->copy(),
        };
    }
}
