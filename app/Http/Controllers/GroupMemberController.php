<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;

class GroupMemberController extends Controller
{
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
    public function destroy(Group $group, GroupMember $member)
    {
        $userId = Auth::id();

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
        ]);
    }
}