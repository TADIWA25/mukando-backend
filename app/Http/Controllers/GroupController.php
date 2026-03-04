<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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

        // preload all member and contribution data for the groups in a single query set
        $groupIds = $groups->pluck('id')->all();

        $allMembers = GroupMember::query()
            ->with('user:id,name,phone')
            ->whereIn('group_id', $groupIds)
            ->get()
            ->groupBy('group_id');

        $data = $groups->map(function (Group $group) use ($memberships, $userId, $allMembers) {
            $membership = $memberships->get($group->id);
            $isAdmin = $membership?->role === 'admin';

            // ignore cycles entirely when collecting contributions; grab any record for the group
            $contributions = Contribution::query()
                ->where('group_id', $group->id)
                ->get()
                ->groupBy('user_id')
                ->mapWithKeys(fn ($grouped, $userId) => [$userId => $grouped->first()]);

            $members = $allMembers->get($group->id, collect());

            $memberPayload = $members->map(function (GroupMember $member) use ($contributions) {
                $contribution = $contributions->get($member->user_id);
                // determine status based on amount_paid rather than stored status if present
                $status = 'pending';
                if ($contribution) {
                    $status = (float) $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
                }

                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->user?->name,
                    'phone' => $member->user?->phone,
                    'role' => $member->role,
                    'joined_at' => optional($member->created_at)->toDateTimeString(),
                    'contribution_status' => $status,
                    'paid_this_cycle' => $status === 'paid',
                    'contribution_amount' => $contribution
                        ? (float) $contribution->amount_paid
                        : 0,
                ];
            })->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'target_amount' => number_format((float) $group->target_amount, 2, '.', ''),
                'contribution_amount' => number_format((float) $group->contribution_amount, 2, '.', ''),
                'interest_rate' => number_format((float) $group->interest_rate, 2, '.', ''),
                'frequency' => $group->frequency,
                'status' => $group->status,
                'total_collected' => number_format((float) $group->total_collected, 2, '.', ''),
                'role' => $membership?->role,
                'contribution_status' => $contributions->get($userId)?->status ?? 'pending',
                'invite_code' => $group->invite_code,
                'can_invite' => $isAdmin,
                'members' => $memberPayload,
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
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'contribution_amount' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'status' => ['sometimes', 'in:active,completed,cancelled'],
            'start_date' => ['required', 'date'],
        ]);

        $userId = $request->user()->id;

        try {
            $group = DB::transaction(function () use ($validated, $userId) {
                $group = Group::query()->create([
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                    'target_amount' => $validated['target_amount'],
                    'contribution_amount' => $validated['contribution_amount'],
                    'interest_rate' => $validated['interest_rate'] ?? 0,
                    'frequency' => $validated['frequency'],
                    'status' => $validated['status'] ?? 'active',
                    'created_by' => $userId,
                ]);

                GroupMember::query()->create([
                    'group_id' => $group->id,
                    'user_id' => $userId,
                    'role' => 'admin',
                ]);

                Contribution::query()->create([
                    'group_id' => $group->id,
                    'user_id' => $userId,
                    'status' => 'pending',
                ]);

                return $group;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot duplicate a contribution for this member.',
                ], Response::HTTP_CONFLICT);
            }
            throw $e;
        }

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

        // ignore cycles when fetching contributions for show
        $members = GroupMember::query()
            ->with('user:id,name,phone')
            ->where('group_id', $group->id)
            ->get();

        $contributions = Contribution::query()
            ->where('group_id', $group->id)
            ->get()
            ->keyBy('user_id');

        $memberPayload = $members->map(function (GroupMember $member) use ($contributions) {
            $contribution = $contributions->get($member->user_id);
            // determine status based on amount_paid rather than stored status if present
            $status = 'pending';
            if ($contribution) {
                $status = (float) $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
            }

            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'phone' => $member->user?->phone,
                'role' => $member->role,
                'joined_at' => optional($member->created_at)->toDateTimeString(),
                'contribution_status' => $status,
                'paid_this_cycle' => $status === 'paid',
                'contribution_amount' => $contribution
                    ? (float) $contribution->amount_paid
                    : 0,
            ];
        })->values();

        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'target_amount' => number_format((float) $group->target_amount, 2, '.', ''),
            'contribution_amount' => number_format((float) $group->contribution_amount, 2, '.', ''),
            'interest_rate' => number_format((float) $group->interest_rate, 2, '.', ''),
            'frequency' => $group->frequency,
            'status' => $group->status,
            'total_collected' => number_format((float) $group->total_collected, 2, '.', ''),
            'invite_code' => $isAdmin ? $group->invite_code : null,
            'total_contributed' => number_format((float) $totalContributed, 2, '.', ''),
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
