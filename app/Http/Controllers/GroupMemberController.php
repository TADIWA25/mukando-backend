<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class GroupMemberController extends Controller
{
    public function index(Group $group): JsonResponse
    {
        $members = $group->members()->with('user')->get();

        $contributions = Contribution::query()
            ->where('group_id', $group->id)
            ->get()
            ->keyBy('user_id');

        $data = $members->map(function (GroupMember $member) use ($contributions) {
            $contribution = $contributions->get($member->user_id);
            $status = $contribution?->status ?? 'pending';
            $amount = $contribution?->amount_paid ?? 0;

            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'phone' => $member->user?->phone,
                'role' => $member->role,
                'joined_at' => optional($member->created_at)->toDateTimeString(),
                'contribution_status' => $status,
                'payment_status' => $status,
                'contribution_amount' => number_format((float) $amount, 2, '.', ''),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'members' => $data,
        ]);
    }

    public function promote(Request $request, Group $group, int $member): JsonResponse
    {
        $memberRecord = GroupMember::query()
            ->where('group_id', $group->id)
            ->where(function ($query) use ($member) {
                $query->where('id', $member)
                    ->orWhere('user_id', $member);
            })
            ->firstOrFail();

        $isAdmin = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        if (! $isAdmin) {
            abort(403, 'Only group admins can promote members.');
        }

        DB::transaction(function () use ($group, $memberRecord) {
            GroupMember::query()
                ->where('group_id', $group->id)
                ->where('role', 'admin')
                ->update(['role' => 'member']);

            $memberRecord->update(['role' => 'admin']);
        });

        return response()->json([
            'status' => true,
            'message' => 'Member promoted to admin.',
        ]);
    }

    public function destroy(Request $request, Group $group, int $member): JsonResponse
    {
        $memberRecord = GroupMember::query()
            ->where('group_id', $group->id)
            ->where(function ($query) use ($member) {
                $query->where('id', $member)
                    ->orWhere('user_id', $member);
            })
            ->firstOrFail();

        $requestingUserId = $request->user()->id;

        $admin = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $requestingUserId)
            ->where('role', 'admin')
            ->exists();

        if (! $admin) {
            abort(403, 'Only group admins can remove members.');
        }

        if ($memberRecord->user_id === $requestingUserId) {
            return response()->json([
                'status' => false,
                'message' => 'Admins cannot remove themselves.',
            ], 400);
        }

        $memberRecord->delete();

        return response()->json([
            'status' => true,
            'message' => 'Member removed from group.',
        ]);
    }

    // allow frontend to update a member's contribution record/status
    public function updatePayment(Request $request, Group $group, int $member): JsonResponse
    {
        $validated = $request->validate([
            'contribution_amount' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['nullable', 'in:pending,paid'],
        ]);

        $memberRecord = GroupMember::query()
            ->where('group_id', $group->id)
            ->where(function ($query) use ($member) {
                $query->where('id', $member)
                    ->orWhere('user_id', $member);
            })
            ->firstOrFail();

        $contribution = Contribution::query()
            ->firstOrNew([
                'group_id' => $group->id,
                'user_id' => $memberRecord->user_id,
            ]);

        if ($contribution->status === 'paid' && $request->input('status') !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Member has already paid.',
            ], 400);
        }

        if (array_key_exists('contribution_amount', $validated)) {
            $contribution->amount_paid = $validated['contribution_amount'];
            // infer a paid status if the caller didn't provide one
            if (! array_key_exists('status', $validated)) {
                $contribution->status = $validated['contribution_amount'] > 0 ? 'paid' : 'pending';
            }
        }
        if (array_key_exists('status', $validated)) {
            $contribution->status = $validated['status'];
        }

        try {
            $contribution->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot duplicate a contribution for this member.',
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment updated.',
        ]);
    }
}
