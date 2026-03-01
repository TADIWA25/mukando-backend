<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContributionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Contribution::query()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'cycle_id' => ['required', 'exists:contribution_cycles,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $isAdmin = GroupMember::query()
            ->where('group_id', $validated['group_id'])
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        if (! $isAdmin) {
            return response()->json([
                'message' => 'Only group admins can record payments.',
            ], Response::HTTP_FORBIDDEN);
        }

        $alreadyPaid = Contribution::query()
            ->where('user_id', $validated['user_id'])
            ->where('group_id', $validated['group_id'])
            ->where('cycle_id', $validated['cycle_id'])
            ->where('status', 'paid')
            ->exists();

        if ($alreadyPaid) {
            return response()->json([
                'message' => 'This member has already paid for this period.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $contribution = Contribution::query()->create([
            'user_id' => $validated['user_id'],
            'group_id' => $validated['group_id'],
            'cycle_id' => $validated['cycle_id'],
            'amount_paid' => $validated['amount'],
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Contribution recorded successfully.',
            'data' => $contribution,
        ], Response::HTTP_CREATED);
    }
}
