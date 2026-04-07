<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    public function index(Request $request, Group $group): JsonResponse
    {
        $member = $group->members()->where('user_id', $request->user()->id)->first();

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        if ($member->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Only group admins can view loan requests.',
            ], 403);
        }

        $applications = LoanApplication::query()
            ->where('group_id', $group->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(function (LoanApplication $application) {
                return [
                    'id' => $application->id,
                    'user_id' => $application->user_id,
                    'name' => $application->user?->name,
                    'amount' => number_format((float) $application->amount, 2, '.', ''),
                    'duration_months' => $application->duration_months,
                    'reason' => $application->reason,
                    'requested_due_date' => $application->requested_due_date?->toDateString(),
                    'status' => $application->status,
                    'review_notes' => $application->review_notes,
                    'reviewed_at' => $application->reviewed_at?->toDateTimeString(),
                    'created_at' => $application->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Loan applications retrieved successfully',
            'data' => $applications,
        ]);
    }

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

        $memberTotalContributions = $this->getMemberTotalContributions($group->id, $request->user()->id);
        if ((float) $validated['amount'] > $memberTotalContributions) {
            return response()->json([
                'status' => false,
                'message' => 'Loan amount cannot exceed your total paid contributions of $'.number_format($memberTotalContributions, 2).'.',
            ], 422);
        }

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

    private function getMemberTotalContributions(int $groupId, int $userId): float
    {
        return (float) Contribution::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->sum('amount_paid');
    }

    public function update(Request $request, Group $group, LoanApplication $loanApplication): JsonResponse
    {
        $member = $group->members()->where('user_id', $request->user()->id)->first();

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        if ($member->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Only group admins can review loan requests.',
            ], 403);
        }

        if ($loanApplication->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan application does not belong to this group.',
            ], 403);
        }

        if ($loanApplication->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'This loan request has already been reviewed.',
            ], 400);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'approved') {
            $activeLoan = Loan::query()
                ->where('group_id', $loanApplication->group_id)
                ->where('user_id', $loanApplication->user_id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($activeLoan) {
                return response()->json([
                    'status' => false,
                    'message' => 'This member already has an active loan in this group.',
                ], 400);
            }

            $availablePool = $this->getAvailablePoolAmount($group->id);
            if ((float) $loanApplication->amount > $availablePool) {
                return response()->json([
                    'status' => false,
                    'message' => 'Loan amount exceeds the available shared pool of $'.number_format($availablePool, 2).'.',
                ], 422);
            }

            $loan = DB::transaction(function () use ($group, $loanApplication, $validated) {
                $groupInterestRate = (float) ($loanApplication->group?->interest_rate ?? 0) / 100;
                $durationMonths = max((int) ($loanApplication->duration_months ?? 1), 1);
                $interest = (float) $loanApplication->amount * $groupInterestRate * $durationMonths;
                $totalAmount = (float) $loanApplication->amount + $interest;
                $dueDate = Carbon::now()->addMonths($durationMonths);

                $loan = Loan::query()->create([
                    'user_id' => $loanApplication->user_id,
                    'group_id' => $loanApplication->group_id,
                    'amount' => $loanApplication->amount,
                    'interest' => $interest,
                    'total_amount' => $totalAmount,
                    'due_date' => $dueDate,
                    'status' => 'approved',
                ]);

                $loanApplication->update([
                    'status' => 'approved',
                    'loan_id' => $loan->id,
                    'review_notes' => $validated['review_notes'] ?? null,
                    'reviewed_at' => now(),
                    'requested_due_date' => $loanApplication->requested_due_date ?? $dueDate->toDateString(),
                ]);

                return $loan;
            });

            $this->syncGroupPoolAmount($group->id);

            return response()->json([
                'status' => true,
                'message' => 'Loan request approved successfully',
                'data' => [
                    'application_id' => $loanApplication->id,
                    'loan_id' => $loan->id,
                    'status' => 'approved',
                ],
            ]);
        }

        $loanApplication->update([
            'status' => 'rejected',
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Loan request rejected successfully',
            'data' => [
                'application_id' => $loanApplication->id,
                'status' => 'rejected',
            ],
        ]);
    }

    private function getAvailablePoolAmount(int $groupId): float
    {
        $totalContributions = (float) Contribution::query()
            ->where('group_id', $groupId)
            ->where('status', 'paid')
            ->sum('amount_paid');

        $totalDisbursed = (float) Loan::query()
            ->where('group_id', $groupId)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        $totalRepayments = (float) LoanPayment::query()
            ->whereHas('loan', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            })
            ->sum('amount');

        return max(0, $totalContributions - $totalDisbursed + $totalRepayments);
    }

    private function syncGroupPoolAmount(int $groupId): void
    {
        Group::query()
            ->where('id', $groupId)
            ->update(['total_collected' => $this->getAvailablePoolAmount($groupId)]);
    }
}
