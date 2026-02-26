<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;

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
            'contribution_amount' => 'nullable|numeric',
            'frequency' => 'nullable|in:weekly,monthly,bi-monthly,yearly',
            'interest_rate' => 'nullable|numeric',
        ]);

        $group = Group::create([
            'name' => $request->name,
            'type' => $request->type,
            'contribution_amount' => $request->contribution_amount,
            'frequency' => $request->frequency,
            'interest_rate' => $request->interest_rate,
            'created_by' => $request->user()->id,
        ]);

        GroupMember::create([
            'user_id' => $request->user()->id,
            'group_id' => $group->id,
            'role' => 'admin'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Group created successfully',
            'data' => $group->load('members.user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $group = Group::with(['members.user', 'contributions', 'loans'])->find($id);

        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Group not found'
            ], 404);
        }

        // Check if user is a member of the group
        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        if (!$isMember) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Group details retrieved successfully',
            'data' => $group
        ], 200);
    }

    public function join(Request $request)
    {
        $request->validate(['group_id' => 'required|exists:groups,id']);
        
        $alreadyMember = GroupMember::where('user_id', $request->user()->id)
            ->where('group_id', $request->group_id)
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'status' => false,
                'message' => 'You are already a member of this group'
            ], 400);
        }

        $member = GroupMember::create([
            'user_id' => $request->user()->id,
            'group_id' => $request->group_id,
            'role' => 'member'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Joined group successfully',
            'data' => $member->load('group')
        ], 200);
    }
}