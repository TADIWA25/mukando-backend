<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Contribution;
=======
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
use Illuminate\Support\Facades\DB;

class GroupInviteController extends Controller
{
<<<<<<< HEAD
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
=======
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'size:6'],
        ]);

        $group = Group::query()
            ->where('invite_code', strtoupper($validated['invite_code']))
            ->firstOrFail();

        $userId = $request->user()->id;

        DB::transaction(function () use ($group, $userId) {
            GroupMember::query()->firstOrCreate(
                [
                    'group_id' => $group->id,
                    'user_id' => $userId,
                ],
                [
                    'role' => 'member',
                ]
            );
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)

            $openCycleIds = $group->cycles()
                ->where('status', 'open')
                ->pluck('id');

            foreach ($openCycleIds as $cycleId) {
<<<<<<< HEAD
                Contribution::firstOrCreate([
=======
                Contribution::query()->firstOrCreate([
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
                    'group_id' => $group->id,
                    'cycle_id' => $cycleId,
                    'user_id' => $userId,
                ], [
                    'status' => 'pending',
                ]);
            }
        });

        return response()->json([
<<<<<<< HEAD
            'status' => 'success',
            'message' => 'You have joined the group!',
            'group' => $group,
        ], 200);
=======
            'status' => true,
            'message' => 'Joined group successfully.',
        ]);
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    }
}
