<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
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
=======
use App\Http\Requests\StoreContributionRequest;
use App\Http\Requests\UpdateContributionRequest;
use App\Models\Contribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Contribution::query()->latest()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContributionRequest $request): JsonResponse
    {
        $contribution = Contribution::create($request->validated());

        return response()->json($contribution, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contribution $contribution): JsonResponse
    {
        return response()->json($contribution);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContributionRequest $request, Contribution $contribution): JsonResponse
    {
        $contribution->update($request->validated());

        return response()->json($contribution->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contribution $contribution): Response
    {
        $contribution->delete();

        return response()->noContent();
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    }
}
