<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupInviteController extends Controller
{
    public function show(Request $request, string $code): JsonResponse
    {
        $group = Group::query()
            ->withCount('members')
            ->where('invite_code', strtoupper($code))
            ->first();

        if (! $group) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid invite code',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'contribution_amount' => $group->contribution_amount,
                'frequency' => $group->frequency,
                'members_count' => $group->members_count,
            ],
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $inviteCode = strtoupper(trim($validated['invite_code']));

        $group = Group::query()->where('invite_code', $inviteCode)->first();
        if (! $group) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid invite code',
            ], 404);
        }

        $userId = $request->user()->id;

        $existing = GroupMember::query()
            ->with('user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'You are already a member of this group',
                'member' => [
                    'id' => $existing->id,
                    'user_id' => $existing->user_id,
                    'name' => $existing->user?->name,
                    'phone' => $existing->user?->phone,
                    'role' => $existing->role,
                ],
            ], 409);
        }

        $member = DB::transaction(function () use ($group, $userId) {
            $member = GroupMember::query()->firstOrCreate(
                [
                    'group_id' => $group->id,
                    'user_id' => $userId,
                ],
                [
                    'role' => 'member',
                ]
            );

            $hasCycleSchema = Schema::hasTable('contribution_cycles')
                && Schema::hasColumn('contributions', 'cycle_id')
                && Schema::hasColumn('contributions', 'status');

            if ($hasCycleSchema) {
                $openCycleIds = $group->cycles()
                    ->where('status', 'open')
                    ->pluck('id');

                foreach ($openCycleIds as $cycleId) {
                    Contribution::query()->firstOrCreate(
                        [
                            'group_id' => $group->id,
                            'cycle_id' => $cycleId,
                            'user_id' => $userId,
                        ],
                        [
                            'status' => 'pending',
                        ]
                    );
                }
            }

            return $member;
        });

        $member->load('user');

        return response()->json([
            'status' => true,
            'message' => 'You have joined the group!',
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'invite_code' => $group->invite_code,
                'members_count' => $group->members()->count(),
            ],
            'member' => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'phone' => $member->user?->phone,
                'role' => $member->role,
            ],
        ]);
    }
}
