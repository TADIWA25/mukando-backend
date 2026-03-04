<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = $request->user()->groups()->with(['members.user'])->get();

        $data = $groups->map(function ($group) {
            // contributions query simply fetches all rows for the group;
            // cycles are no longer relevant to the front end so we ignore them.
            $contributions = Contribution::where('group_id', $group->id)
                ->get()
                ->groupBy('user_id')
                ->mapWithKeys(fn ($grouped, $userId) => [$userId => $grouped->first()]);

            $totalContributed = Contribution::query()
                ->where('group_id', $group->id)
                ->where('status', 'paid')
                ->sum('amount_paid');

            $groupArray = $group->toArray();
            $groupArray['total_contributed'] = number_format((float) $totalContributed, 2, '.', '');

            $groupArray['members'] = $group->members->map(function ($member) use ($contributions) {
                $contribution = $contributions->get($member->user_id);
                // determine status based on amount_paid rather than stored status if present
                $status = 'pending';
                if ($contribution) {
                    $status = $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
                }

                $memberArray = $member->toArray();
                $memberArray['contribution_status'] = $status;
                $memberArray['payment_status'] = $status;
                // always source the amount from the contribution record if it exists
                $memberArray['contribution_amount'] = $contribution
                    ? (float) $contribution->amount_paid
                    : 0;
                $memberArray['paid_this_cycle'] = $status === 'paid';

                return $memberArray;
            })->values()->toArray();

            return $groupArray;
        });

        return response()->json([
            'status' => true,
            'message' => 'Groups retrieved successfully',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:contribution,rounds,shared',
            'target_amount' => 'required|numeric|min:0.01',
            'contribution_amount' => 'required|numeric|min:0.01|lte:target_amount',
            'interest_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
        ]);

        try {
            $group = DB::transaction(function () use ($request) {
                $group = Group::create([
                    'name' => $request->name,
                    'type' => $request->type,
                    'target_amount' => $request->target_amount,
                    'contribution_amount' => $request->contribution_amount,
                    'interest_rate' => $request->interest_rate ?? 0,
                    'frequency' => $request->frequency,
                    'status' => 'active',
                    'created_by' => $request->user()->id,
                ]);

                GroupMember::create([
                    'user_id' => $request->user()->id,
                    'group_id' => $group->id,
                    'role' => 'admin',
                ]);

                Contribution::create([
                    'group_id' => $group->id,
                    'user_id' => $request->user()->id,
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
            'message' => 'Group created successfully',
            'data' => array_merge(
                $group->load('members.user')->toArray(),
                ['invite_code' => $group->invite_code]
            ),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $group = Group::with(['members.user'])->find($id);

        if (! $group) {
            return response()->json([
                'status' => false,
                'message' => 'Group not found',
            ], 404);
        }

        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        if (! $isMember) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to group',
            ], 403);
        }

        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        $totalContributed = Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'paid')
            ->sum('amount_paid');

        // fetch contributions for the group
        $contributions = Contribution::query()
            ->where('group_id', $group->id)
            ->get()
            ->groupBy('user_id')
            ->mapWithKeys(fn ($grouped, $userId) => [$userId => $grouped->first()]);

        $members = $group->members->map(function ($member) use ($contributions) {
            $contribution = $contributions->get($member->user_id);
            $status = 'pending';
            if ($contribution) {
                $status = (float) $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
            }

            return [
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role' => $member->role,
                'paid_this_cycle' => $status === 'paid',
                'contribution_status' => $status,
                'payment_status' => $status,
                'contribution_amount' => $contribution
                    ? (float) $contribution->amount_paid
                    : 0,
            ];
        })->values();

        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'target_amount' => $group->target_amount,
            'contribution_amount' => $group->contribution_amount,
            'interest_rate' => $group->interest_rate,
            'frequency' => $group->frequency,
            'status' => $group->status,
            'total_contributed' => number_format((float) $totalContributed, 2, '.', ''),
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
