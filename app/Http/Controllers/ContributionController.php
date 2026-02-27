<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ContributionController extends Controller
{
    public function recordContribution(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
            'cycle_id' => 'required|exists:contribution_cycles,id',
            'amount' => 'required|numeric|min:0',
        ]);

        // Ensure each member is marked paid at most once per cycle.
        $alreadyPaid = Contribution::where('user_id', $request->user_id)
            ->where('group_id', $request->group_id)
            ->where('cycle_id', $request->cycle_id)
            ->where('status', 'paid')
            ->exists();

        if ($alreadyPaid) {
            return response()->json([
                'message' => 'This member has already paid for this period.'
            ], 400);
        }

        $contribution = Contribution::create([
            'user_id' => $request->user_id,
            'group_id' => $request->group_id,
            'cycle_id' => $request->cycle_id,
            'amount_paid' => $request->amount,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Contribution recorded successfully.',
            'data' => $contribution
        ]);
    }
}
