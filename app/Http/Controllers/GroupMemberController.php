<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    public function index(Group $group): JsonResponse
    {
        $members = $group->members()->with('user')->get();

        $data = $members->map(function (GroupMember $member) {
            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'phone' => $member->user?->phone,
                'role' => $member->role,
                'joined_at' => optional($member->created_at)->toDateTimeString(),
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

        if ($memberRecord->user_id === $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Admins cannot change their own role.',
            ], 400);
        }

        $memberRecord->update(['role' => 'admin']);

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
}
