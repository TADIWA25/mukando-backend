<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\Auth;

class GroupInviteController extends Controller
{
    public function join(Request $request)
    {
        // Validate input
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        // Find the group
        $group = Group::where('invite_code', $request->invite_code)->first();
        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid invite code'
            ], 404);
        }

        $userId = Auth::id(); // Ensure user is logged in via Sanctum or other auth

        // Check if already a member
        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already a member of this group'
            ], 400);
        }

        // Add user to group_members
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $userId,
            'role' => 'member',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'You have joined the group!',
            'group' => $group,
        ], 200);
    }
}