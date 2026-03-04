<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * Get all loans for a specific group.
     */
    public function index(Group $group): JsonResponse
    {
        // Check if group is of type 'shared'
        if ($group->type !== 'shared') {
            return response()->json([
                'status' => false,
                'message' => 'Loans are only available for shared groups.',
            ], 403);
        }

        $loans = Loan::where('group_id', $group->id)
            ->with('user')
            ->get();

        $data = $loans->map(function (Loan $loan) {
            $isPaid = $loan->status === 'paid';
            
            return [
                'id' => $loan->id,
                'user_id' => $loan->user_id,
                'name' => $loan->user?->name,
                'amount' => number_format((float) $loan->amount, 2, '.', ''),
                'interest' => number_format((float) $loan->interest, 2, '.', ''),
                'total_amount' => number_format((float) $loan->total_amount, 2, '.', ''),
                'due_date' => $loan->due_date,
                'status' => $loan->status,
                'payment_status' => $isPaid ? 'paid' : 'pending',
                'amount_owing' => number_format((float) $loan->total_amount, 2, '.', ''),
            ];
        });

        return response()->json([
            'status' => true,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'loans' => $data,
        ]);
    }

    /**
     * Update the payment status of a loan.
     */
    public function updatePayment(Request $request, Group $group, Loan $loan): JsonResponse
    {
        // Ensure the loan belongs to the group
        if ($loan->group_id !== $group->id) {
            return response()->json([
                'status' => false,
                'message' => 'Loan does not belong to this group.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $newStatus = $validated['status'];

        // If the loan is already paid, and we're not explicitly setting it back to something else
        if ($loan->status === 'paid' && $newStatus !== 'pending' && $newStatus !== 'approved') {
            return response()->json([
                'status' => false,
                'message' => 'Loan is already marked as paid.',
            ], 400);
        }

        $loan->status = $newStatus;
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan status updated.',
            'loan' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'payment_status' => $loan->status === 'paid' ? 'paid' : 'pending',
            ],
        ]);
    }
}
