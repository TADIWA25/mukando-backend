<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Loan;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoanApplicationController extends Controller
{
    /**
     * Submit a loan application. This stores into loan_applications, not loans.
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $member = $group->members()->where('user_id', $request->user()->id)->first();

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        if ($group->type !== 'shared') {
            return response()->json([
                'status' => false,
                'message' => 'Loans are only available for shared groups.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $groupMaxContribution = (float) ($group->contribution_amount ?? 0);
        if ((float) $validated['amount'] > $groupMaxContribution) {
            return response()->json([
                'status' => false,
                'message' => 'Loan amount cannot exceed this group\'s contribution limit of $'.number_format($groupMaxContribution, 2).'.',
            ], 422);
        }

        $durationMonths = $validated['duration_months'] ?? 1;

        $hasActiveLoan = Loan::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasActiveLoan) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an active loan in this group.',
            ], 400);
        }

        $hasPendingApplication = LoanApplication::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingApplication) {
            return response()->json([
                'status' => false,
                'message' => 'You already have a pending loan application in this group.',
            ], 400);
        }

        $application = LoanApplication::query()->create([
            'user_id' => $request->user()->id,
            'group_id' => $group->id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'duration_months' => $durationMonths,
            'requested_due_date' => Carbon::now()->addMonths($durationMonths)->toDateString(),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Loan application submitted successfully',
            'data' => [
                'id' => $application->id,
                'amount' => number_format((float) $application->amount, 2, '.', ''),
                'duration_months' => $application->duration_months,
                'requested_due_date' => $application->requested_due_date?->toDateString(),
                'status' => $application->status,
            ],
        ], 201);
    }
}
