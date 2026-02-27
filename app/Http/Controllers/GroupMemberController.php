<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
=======
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
use Illuminate\Support\Facades\DB;

class GroupMemberController extends Controller
{
<<<<<<< HEAD
    public function index(Group $group)
    {
        // Load all members of the group with user info
        $members = $group->members()->with('user')->get();

        // Transform the data to include user info nicely
        $data = $members->map(function($member) {
            return [
                'id' => $member->id,
                'user_id' => $member->user->id,
                'name' => $member->user->name,
                'phone' => $member->user->phone,
                'role' => $member->role,
                'joined_at' => $member->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'group_id' => $group->id,
            'group_name' => $group->name,
            'members' => $data,
        ]);
    }

    public function promote(Request $request, Group $group, GroupMember $member)
    {
        if ($member->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Member does not belong to this group',
            ], 404);
        }

        $isAdmin = GroupMember::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        if (!$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Only group admins can promote members',
            ], 403);
        }

        DB::transaction(function () use ($group, $member) {
            GroupMember::where('group_id', $group->id)
                ->where('role', 'admin')
                ->update(['role' => 'member']);

            $member->update(['role' => 'admin']);
=======
    public function promote(Request $request, Group $group, int $member): JsonResponse
    {
        $memberRecord = GroupMember::query()
            ->where('group_id', $group->id)
            ->where(function ($query) use ($member) {
                $query->where('id', $member)
                    ->orWhere('user_id', $member);
            })
            ->firstOrFail();

        $currentUserId = $request->user()->id;

        $requestingMembership = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $currentUserId)
            ->first();

        if (! $requestingMembership || $requestingMembership->role !== 'admin') {
            abort(403, 'Only an admin can promote members.');
        }

        DB::transaction(function () use ($group, $memberRecord) {
            GroupMember::query()
                ->where('group_id', $group->id)
                ->where('role', 'admin')
                ->update(['role' => 'member']);

            $memberRecord->update(['role' => 'admin']);
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
        });

        return response()->json([
            'status' => true,
<<<<<<< HEAD
            'message' => 'Member promoted to admin successfully',
            'data' => [
                'group_id' => $group->id,
                'user_id' => $member->user_id,
                'role' => 'admin',
            ],
        ]);
    }

    public function destroy(Request $request, Group $group, GroupMember $member)
    {
        if ($member->group_id !== $group->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member does not belong to this group'
            ], 404);
        }

        $userId = $request->user()->id;

        // Check if the logged-in user is an admin of this group
        $admin = GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->first();

        if (!$admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only group admins can remove members'
            ], 403);
        }

        // Prevent admin from deleting themselves (optional)
        if ($member->user_id == $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admins cannot remove themselves'
            ], 400);
        }

        // Remove the member
        $member->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed from group'
=======
            'message' => 'Member promoted to admin.',
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
        ]);
    }
}
