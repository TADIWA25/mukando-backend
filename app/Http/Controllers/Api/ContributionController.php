<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

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
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Check if a contribution already exists for this user in this group
        $existingContribution = Contribution::query()
            ->where('user_id', $validated['user_id'])
            ->where('group_id', $validated['group_id'])
            ->first();

        if ($existingContribution) {
            if ($existingContribution->status === 'paid') {
                return response()->json([
                    'status' => false,
                    'message' => 'This member has already paid their contribution.',
                ], Response::HTTP_BAD_REQUEST);
            } else {
                // If there's a pending contribution, update it to paid
                return DB::transaction(function () use ($validated, $existingContribution) {
                    $existingContribution->update([
                        'amount_paid' => $validated['amount'],
                        'status' => 'paid',
                        'paid_at' => Carbon::now(),
                    ]);

                    // Update GroupMember record
                    GroupMember::query()
                        ->where('group_id', $validated['group_id'])
                        ->where('user_id', $validated['user_id'])
                        ->update([
                            'has_paid' => true,
                            'amount_paid' => DB::raw("amount_paid + {$validated['amount']}"),
                            'last_payment_at' => Carbon::now(),
                        ]);

                    // Update Group total_collected
                    Group::query()
                        ->where('id', $validated['group_id'])
                        ->increment('total_collected', $validated['amount']);

                    return response()->json([
                        'status' => true,
                        'message' => 'Contribution recorded successfully.',
                        'data' => $existingContribution->fresh(),
                    ], Response::HTTP_CREATED);
                });
            }
        }

        // No existing contribution - create a new one
        try {
            return DB::transaction(function () use ($validated) {
                $contribution = Contribution::create([
                    'user_id' => $validated['user_id'],
                    'group_id' => $validated['group_id'],
                    'amount_paid' => $validated['amount'],
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);

                // Update GroupMember record
                GroupMember::query()
                    ->where('group_id', $validated['group_id'])
                    ->where('user_id', $validated['user_id'])
                    ->update([
                        'has_paid' => true,
                        'amount_paid' => DB::raw("amount_paid + {$validated['amount']}"),
                        'last_payment_at' => Carbon::now(),
                    ]);

                // Update Group total_collected
                Group::query()
                    ->where('id', $validated['group_id'])
                    ->increment('total_collected', $validated['amount']);

                return response()->json([
                    'status' => true,
                    'message' => 'Contribution recorded successfully.',
                    'data' => $contribution,
                ], Response::HTTP_CREATED);
            });
        } catch (QueryException $e) {
            // Check for duplicate entry error (1062 for MySQL)
            if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                return response()->json([
                    'status' => false,
                    'message' => 'This member already has a contribution record for this group.',
                ], Response::HTTP_CONFLICT);
            }
            throw $e;
        }
    }
}

