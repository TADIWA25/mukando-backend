<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Contribution;
use Illuminate\Support\Facades\DB;

class GroupInviteController extends Controller
{
    public function join(Request $request)
    {
        // Validate input
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        // Find the group
        $group = Group::where('invite_code', strtoupper($request->invite_code))->first();
        if (!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid invite code'
            ], 404);
        }

        $userId = $request->user()->id;

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

        DB::transaction(function () use ($group, $userId) {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $userId,
                'role' => 'member',
            ]);

            $openCycleIds = $group->cycles()
                ->where('status', 'open')
                ->pluck('id');

            foreach ($openCycleIds as $cycleId) {
                Contribution::firstOrCreate([
                    'group_id' => $group->id,
                    'cycle_id' => $cycleId,
                    'user_id' => $userId,
                ], [
                    'status' => 'pending',
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'You have joined the group!',
            'group' => $group,
        ], 200);
    }
}
