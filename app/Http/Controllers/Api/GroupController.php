<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Contribution;
use App\Models\ContributionCycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = $request->user()->groups()->with('members.user')->get();
        return response()->json([
            'status' => true,
            'message' => 'Groups retrieved successfully',
            'data' => $groups
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:contribution,rounds,shared',
            'target_amount' => 'required|numeric|min:0.01',
            'contribution_amount' => 'required|numeric|min:0.01|lte:target_amount',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
        ]);

        $group = DB::transaction(function () use ($request) {
            $group = Group::create([
                'name' => $request->name,
                'type' => $request->type,
                'target_amount' => $request->target_amount,
                'contribution_amount' => $request->contribution_amount,
                'frequency' => $request->frequency,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            GroupMember::create([
                'user_id' => $request->user()->id,
                'group_id' => $group->id,
                'role' => 'admin',
            ]);

            $startDate = Carbon::parse($request->start_date);
            $cycleCount = (int) ceil((float) $request->target_amount / (float) $request->contribution_amount);
            $cycleCount = max(1, $cycleCount);

            for ($i = 1; $i <= $cycleCount; $i++) {
                $dueDate = match ($request->frequency) {
                    'daily' => $startDate->copy()->addDays($i - 1),
                    'weekly' => $startDate->copy()->addWeeks($i - 1),
                    'monthly' => $startDate->copy()->addMonthsNoOverflow($i - 1),
                };

                $cycle = ContributionCycle::create([
                    'group_id' => $group->id,
                    'cycle_number' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'open',
                ]);

                Contribution::create([
                    'group_id' => $group->id,
                    'cycle_id' => $cycle->id,
                    'user_id' => $request->user()->id,
                    'status' => 'pending',
                ]);
            }

            return $group;
        });

        return response()->json([
            'status' => true,
            'message' => 'Group created successfully',
            'data' => $group->load('members.user'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $group = Group::with(['members.user'])->find($id);

        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Group not found'
            ], 404);
        }

        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        if (!$isMember) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        $currentCycle = $group->cycles()
            ->where('status', 'open')
            ->orderBy('cycle_number')
            ->first();

        $totalContributed = Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'paid')
            ->sum('amount_paid');

        $paidUserIds = collect();
        if ($currentCycle) {
            $paidUserIds = Contribution::query()
                ->where('group_id', $group->id)
                ->where('cycle_id', $currentCycle->id)
                ->where('status', 'paid')
                ->pluck('user_id');
        }

        $members = $group->members->map(function ($member) use ($paidUserIds) {
            return [
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role' => $member->role,
                'paid_this_cycle' => $paidUserIds->contains($member->user_id),
            ];
        })->values();

        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'target_amount' => $group->target_amount,
            'contribution_amount' => $group->contribution_amount,
            'frequency' => $group->frequency,
            'status' => $group->status,
            'total_contributed' => number_format((float) $totalContributed, 2, '.', ''),
            'current_cycle' => $currentCycle ? [
                'id' => $currentCycle->id,
                'cycle_number' => $currentCycle->cycle_number,
                'due_date' => optional($currentCycle->due_date)->toDateString(),
                'status' => $currentCycle->status,
            ] : null,
            'members' => $members,
        ];

        return response()->json([
            'status' => true,
            'data' => $isAdmin
                ? array_merge($data, ['invite_code' => $group->invite_code])
                : $data,
        ], 200);
    }
}
