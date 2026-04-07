<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LoanController extends Controller
{
    /**
     * Get all loans for a specific group with member details.
     * Similar to how contributions are tracked for members.
     */
    public function index(Request $request, Group $group): JsonResponse
    {
        // Check if user is a member of the group
        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        
        if (!$isMember) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        $loans = Loan::where('group_id', $group->id)
            ->with('user')
            ->get();

        $data = $loans->map(function (Loan $loan) {
            $totalPaid = $loan->payments()->sum('amount');
            $remainingBalance = (float) $loan->total_amount - $totalPaid;
            $isOverdue = $loan->status !== 'paid' && now()->greaterThan($loan->due_date);
            
            return [
                'id' => $loan->id,
                'user_id' => $loan->user_id,
                'name' => $loan->user?->name,
                'phone' => $loan->user?->phone,
                'amount' => number_format((float) $loan->amount, 2, '.', ''),
                'interest' => number_format((float) $loan->interest, 2, '.', ''),
                'total_amount' => number_format((float) $loan->total_amount, 2, '.', ''),
                'total_paid' => number_format((float) $totalPaid, 2, '.', ''),
                'remaining_balance' => number_format((float) max(0, $remainingBalance), 2, '.', ''),
                'due_date' => $loan->due_date,
                'status' => $loan->status,
                'payment_status' => $loan->status === 'paid' ? 'paid' : 'pending',
                'is_overdue' => $isOverdue,
                'created_at' => $loan->created_at?->toDateTimeString(),
            ];
        });

        // Calculate group loan summary
        $totalLoansAmount = $loans->sum('total_amount');
        $totalLoansPaid = $loans->sum(function ($loan) {
            return $loan->payments()->sum('amount');
        });
        $activeLoansCount = $loans->where('status', 'pending')->count() + $loans->where('status', 'approved')->count();
        $paidLoansCount = $loans->where('status', 'paid')->count();

        return response()->json([
            'status' => true,
            'message' => 'Loans retrieved successfully',
            'group_id' => $group->id,
            'group_name' => $group->name,
            'loans' => $data,
            'summary' => [
                'total_loans_amount' => number_format((float) $totalLoansAmount, 2, '.', ''),
                'total_paid' => number_format((float) $totalLoansPaid, 2, '.', ''),
                'active_loans_count' => $activeLoansCount,
                'paid_loans_count' => $paidLoansCount,
            ],
        ]);
    }

    /**
     * Get loan details for a specific loan.
     */
    public function show(Request $request, Group $group, Loan $loan): JsonResponse
    {
        // Ensure the loan belongs to the group
        if ($loan->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan does not belong to this group.',
            ], 403);
        }

        // Check if user is a member of the group
        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        
        if (!$isMember) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        $loan->load('user', 'payments');

        $totalPaid = $loan->payments()->sum('amount');
        $remainingBalance = (float) $loan->total_amount - $totalPaid;
        $isOverdue = $loan->status !== 'paid' && now()->greaterThan($loan->due_date);

        $data = [
            'id' => $loan->id,
            'user_id' => $loan->user_id,
            'name' => $loan->user?->name,
            'phone' => $loan->user?->phone,
            'amount' => number_format((float) $loan->amount, 2, '.', ''),
            'interest' => number_format((float) $loan->interest, 2, '.', ''),
            'total_amount' => number_format((float) $loan->total_amount, 2, '.', ''),
            'total_paid' => number_format((float) $totalPaid, 2, '.', ''),
            'remaining_balance' => number_format((float) max(0, $remainingBalance), 2, '.', ''),
            'due_date' => $loan->due_date,
            'status' => $loan->status,
            'payment_status' => $loan->status === 'paid' ? 'paid' : 'pending',
            'is_overdue' => $isOverdue,
            'created_at' => $loan->created_at?->toDateTimeString(),
            'payments' => $loan->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                ];
            }),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Loan details retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Apply for a loan (create new loan).
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        // Check if user is a member of the group
        $member = $group->members()->where('user_id', $request->user()->id)->first();
        
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        // Check if group is of type 'shared' (for loans)
        if ($group->type !== 'shared') {
            return response()->json([
                'status' => false,
                'message' => 'Loans are only available for shared groups.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:36'],
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

        // Calculate interest and total amount based on group's interest rate
        $interestRate = (float) $group->interest_rate / 100;
        $durationMonths = $validated['duration_months'] ?? 1;
        $interest = (float) $validated['amount'] * $interestRate * $durationMonths;
        $totalAmount = (float) $validated['amount'] + $interest;

        // Calculate due date (default: 30 days per month of duration)
        $dueDate = Carbon::now()->addMonths($durationMonths);

        // Check if user already has an active loan in this group
        $existingLoan = Loan::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingLoan) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an active loan in this group.',
            ], 400);
        }

        try {
            $loan = DB::transaction(function () use ($group, $request, $validated, $interest, $totalAmount, $dueDate) {
                return Loan::create([
                    'user_id' => $request->user()->id,
                    'group_id' => $group->id,
                    'amount' => $validated['amount'],
                    'interest' => $interest,
                    'total_amount' => $totalAmount,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ]);
            });
        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create loan. Please try again.',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Loan application submitted successfully',
            'data' => [
                'id' => $loan->id,
                'amount' => number_format((float) $loan->amount, 2, '.', ''),
                'interest' => number_format((float) $loan->interest, 2, '.', ''),
                'total_amount' => number_format((float) $loan->total_amount, 2, '.', ''),
                'due_date' => $loan->due_date,
                'status' => $loan->status,
            ],
        ], 201);
    }

    /**
     * Update loan status (approve/reject/pay).
     */
    public function update(Request $request, Group $group, Loan $loan): JsonResponse
    {
        // Ensure the loan belongs to the group
        if ($loan->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan does not belong to this group.',
            ], 403);
        }

        // Check if user is an admin of the group
        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        // Users can only update their own loans to 'paid' status
        $isOwner = $loan->user_id === $request->user()->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update this loan.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,paid'],
        ]);

        $newStatus = $validated['status'];

        // If the loan is already paid, only admins can change it back
        if ($loan->status === 'paid' && $newStatus !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Loan is already paid.',
            ], 400);
        }

        // If rejecting, only admins can do it
        if ($newStatus === 'rejected' && !$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Only admins can reject loan applications.',
            ], 403);
        }

        // If approving, only admins can do it
        if ($newStatus === 'approved' && !$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Only admins can approve loan applications.',
            ], 403);
        }

        if ($newStatus === 'approved' && $loan->status !== 'approved') {
            $availablePool = $this->getAvailablePoolAmount($group->id);
            if ((float) $loan->amount > $availablePool) {
                return response()->json([
                    'status' => false,
                    'message' => 'Loan amount exceeds the available shared pool of $'.number_format($availablePool, 2).'.',
                ], 422);
            }
        }

        $previousStatus = $loan->status;
        DB::transaction(function () use ($loan, $newStatus) {
            $loan->status = $newStatus;
            $loan->save();
        });

        if ($newStatus === 'approved' && $previousStatus !== 'approved') {
            $this->syncGroupPoolAmount($group->id);
        }

        return response()->json([
            'status' => true,
            'message' => 'Loan status updated successfully',
            'data' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'payment_status' => $loan->status === 'paid' ? 'paid' : 'pending',
            ],
        ]);
    }

    /**
     * Record a loan payment.
     */
    public function recordPayment(Request $request, Group $group, Loan $loan): JsonResponse
    {
        // Ensure the loan belongs to the group
        if ($loan->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan does not belong to this group.',
            ], 403);
        }

        // Only the loan owner or admin can record payment
        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        $isOwner = $loan->user_id === $request->user()->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to record payment for this loan.',
            ], 403);
        }

        if ($loan->status === 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Loan is already fully paid.',
            ], 400);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Record the payment
        $payment = LoanPayment::create([
            'loan_id' => $loan->id,
            'amount' => $validated['amount'],
            'paid_at' => now(),
        ]);
        $this->syncGroupPoolAmount($group->id);

        // Check if loan is now fully paid
        $totalPaid = $loan->payments()->sum('amount');
        if ($totalPaid >= (float) $loan->total_amount) {
            $loan->status = 'paid';
            $loan->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment recorded successfully',
            'data' => [
                'payment_id' => $payment->id,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'loan_status' => $loan->status,
            ],
        ], 201);
    }

    /**
     * Delete/cancel a loan.
     */
    public function destroy(Request $request, Group $group, Loan $loan): JsonResponse
    {
        // Ensure the loan belongs to the group
        if ($loan->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan does not belong to this group.',
            ], 403);
        }

        // Check if user is an admin of the group
        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        // Only the loan owner or admin can delete
        $isOwner = $loan->user_id === $request->user()->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this loan.',
            ], 403);
        }

        // Only pending loans can be cancelled by owner
        if ($loan->status !== 'pending' && !$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'Only pending loans can be cancelled.',
            ], 400);
        }

        // Delete associated payments first
        $loan->payments()->delete();
        $loan->delete();

        return response()->json([
            'status' => true,
            'message' => 'Loan deleted successfully',
        ]);
    }

    /**
     * Get loans for the current user across all groups.
     */
    public function myLoans(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $loans = Loan::where('user_id', $user->id)
            ->with('group')
            ->get()
            ->map(function (Loan $loan) {
                $totalPaid = $loan->payments()->sum('amount');
                $remainingBalance = (float) $loan->total_amount - $totalPaid;
                $isOverdue = $loan->status !== 'paid' && now()->greaterThan($loan->due_date);
                
                return [
                    'id' => $loan->id,
                    'group_id' => $loan->group_id,
                    'group_name' => $loan->group?->name,
                    'amount' => number_format((float) $loan->amount, 2, '.', ''),
                    'interest' => number_format((float) $loan->interest, 2, '.', ''),
                    'total_amount' => number_format((float) $loan->total_amount, 2, '.', ''),
                    'total_paid' => number_format((float) $totalPaid, 2, '.', ''),
                    'remaining_balance' => number_format((float) max(0, $remainingBalance), 2, '.', ''),
                    'due_date' => $loan->due_date,
                    'status' => $loan->status,
                    'payment_status' => $loan->status === 'paid' ? 'paid' : 'pending',
                    'is_overdue' => $isOverdue,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Your loans retrieved successfully',
            'data' => $loans,
        ]);
    }

    private function getMemberTotalContributions(int $groupId, int $userId): float
    {
        return (float) Contribution::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->sum('amount_paid');
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
