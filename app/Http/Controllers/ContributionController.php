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
            'amount' => 'required|numeric|min:0',
        ]);

        // Check if the user already paid for this group this month (or week depending on frequency)
        $alreadyPaid = Contribution::where('user_id', $request->user_id)
            ->where('group_id', $request->group_id)
            ->whereMonth('paid_at', Carbon::now()->month)
            ->whereYear('paid_at', Carbon::now()->year)
            ->exists();

        if ($alreadyPaid) {
            return response()->json([
                'message' => 'This member has already paid for this period.'
            ], 400);
        }

        $contribution = Contribution::create([
            'user_id' => $request->user_id,
            'group_id' => $request->group_id,
            'amount' => $request->amount,
            'paid_at' => Carbon::now(),
        ]);
        // ✅ Update group total
        $group = $contribution->group;
        $total = $group->contributions()->sum('amount');
        $group->update(['total_collected' => $total]);

        return response()->json([
            'message' => 'Contribution recorded successfully.',
            'data' => $contribution
        ]);
    }
}