<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = $request->user()->groups()->with(['members.user'])->get();

        $data = $groups->map(function ($group) {
            $currentCycle = $group->currentContributionCycle();
            $currentCycleId = $currentCycle?->id;
            $currentCycleContributions = $this->getCurrentCycleContributions($group, $currentCycleId);

            $totalContributed = $this->getDisplayedPoolAmount($group);

            $groupArray = $group->toArray();
            $groupArray['total_contributed'] = number_format((float) $totalContributed, 2, '.', '');

            $groupArray['current_cycle'] = $this->serializeCycle($currentCycle);
            $groupArray['members'] = $group->members->map(function ($member) use ($currentCycleContributions) {
                $contribution = $currentCycleContributions->get($member->user_id);
                $status = 'pending';
                if ($contribution) {
                    $status = (float) $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
                }

                $memberArray = $member->toArray();
                $memberArray['contribution_status'] = $status;
                $memberArray['payment_status'] = $status;
                // always source the amount from the contribution record if it exists
                $memberArray['contribution_amount'] = $contribution
                    ? (float) $contribution->amount_paid
                    : 0;
                $memberArray['paid_this_cycle'] = $status === 'paid';

                return $memberArray;
            })->values()->toArray();

            return $groupArray;
        });

        return response()->json([
            'status' => true,
            'message' => 'Groups retrieved successfully',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:contribution,rounds,shared',
            'target_amount' => 'nullable|numeric|min:0.01',
            'contribution_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
        ]);

        if ($validated['type'] === 'contribution' && ! isset($validated['target_amount'])) {
            throw ValidationException::withMessages([
                'target_amount' => 'The target amount field is required for contribution groups.',
            ]);
        }

        if (
            $validated['type'] === 'contribution'
            && (float) $validated['contribution_amount'] > (float) $validated['target_amount']
        ) {
            throw ValidationException::withMessages([
                'contribution_amount' => 'The contribution amount must be less than or equal to the target amount.',
            ]);
        }

        $targetAmount = $validated['target_amount'] ?? $validated['contribution_amount'];

        try {
            $group = DB::transaction(function () use ($request, $validated, $targetAmount) {
                $group = Group::create([
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                    'target_amount' => $targetAmount,
                    'contribution_amount' => $validated['contribution_amount'],
                    'interest_rate' => $validated['interest_rate'] ?? 0,
                    'frequency' => $validated['frequency'],
                    'start_date' => $validated['start_date'],
                    'status' => 'active',
                    'created_by' => $request->user()->id,
                ]);

                GroupMember::create([
                    'user_id' => $request->user()->id,
                    'group_id' => $group->id,
                    'role' => 'admin',
                ]);

                if ($group->type !== 'shared') {
                    $currentCycle = $group->currentContributionCycle();

                    if ($currentCycle) {
                        Contribution::query()->firstOrCreate(
                            [
                                'group_id' => $group->id,
                                'cycle_id' => $currentCycle->id,
                                'user_id' => $request->user()->id,
                            ],
                            [
                                'status' => 'pending',
                            ]
                        );
                    }
                } else {
                    Contribution::create([
                        'group_id' => $group->id,
                        'user_id' => $request->user()->id,
                        'status' => 'pending',
                    ]);
                }

                return $group;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot duplicate a contribution for this member.',
                ], Response::HTTP_CONFLICT);
            }
            throw $e;
        }

        return response()->json([
            'status' => true,
            'message' => 'Group created successfully',
            'data' => array_merge(
                $group->load('members.user')->toArray(),
                ['invite_code' => $group->invite_code]
            ),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $group = Group::with(['members.user'])->find($id);

        if (! $group) {
            return response()->json([
                'status' => false,
                'message' => 'Group not found',
            ], 404);
        }

        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        if (! $isMember) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to group',
            ], 403);
        }

        $isAdmin = $group->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        $totalContributed = Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'paid')
            ->sum('amount_paid');

        $displayedPool = $this->getDisplayedPoolAmount($group);

        $currentCycle = $group->currentContributionCycle();
        $currentCycleId = $currentCycle?->id;
        $contributions = $this->getCurrentCycleContributions($group, $currentCycleId);

        $members = $group->members->map(function ($member) use ($contributions) {
            $contribution = $contributions->get($member->user_id);
            $status = 'pending';
            if ($contribution) {
                $status = (float) $contribution->amount_paid > 0 ? 'paid' : $contribution->status;
            }

            return [
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role' => $member->role,
                'paid_this_cycle' => $status === 'paid',
                'contribution_status' => $status,
                'payment_status' => $status,
                'contribution_amount' => $contribution
                    ? (float) $contribution->amount_paid
                    : 0,
            ];
        })->values();

        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'target_amount' => $group->target_amount,
            'contribution_amount' => $group->contribution_amount,
            'interest_rate' => $group->interest_rate,
            'frequency' => $group->frequency,
            'status' => $group->status,
            'total_contributed' => number_format((float) $displayedPool, 2, '.', ''),
            'current_cycle' => $this->serializeCycle($currentCycle),
            'members' => $members,
        ];

        return response()->json([
            'status' => true,
            'data' => $isAdmin
                ? array_merge($data, ['invite_code' => $group->invite_code])
                : $data,
        ], 200);
    }

    private function getCurrentCycleContributions(Group $group, ?int $currentCycleId)
    {
        $query = Contribution::query()
            ->where('group_id', $group->id);

        if ($group->type === 'shared' || $currentCycleId === null) {
            $query->whereNull('cycle_id');
        } else {
            $query->where('cycle_id', $currentCycleId);
        }

        return $query->get()->keyBy('user_id');
    }

    private function serializeCycle($cycle): ?array
    {
        if (! $cycle) {
            return null;
        }

        return [
            'id' => $cycle->id,
            'cycle_number' => $cycle->cycle_number,
            'due_date' => $cycle->due_date?->toDateString() ?? $cycle->due_date,
            'status' => $cycle->status,
        ];
    }

    private function getDisplayedPoolAmount(Group $group): float
    {
        if ($group->type === 'shared') {
            $totalContributions = (float) Contribution::query()
                ->where('group_id', $group->id)
                ->where('status', 'paid')
                ->sum('amount_paid');

            $totalDisbursed = (float) Loan::query()
                ->where('group_id', $group->id)
                ->whereIn('status', ['approved', 'paid'])
                ->sum('amount');

            $totalRepayments = (float) LoanPayment::query()
                ->whereHas('loan', function ($query) use ($group) {
                    $query->where('group_id', $group->id);
                })
                ->sum('amount');

            return max(0, $totalContributions - $totalDisbursed + $totalRepayments);
        }

        return (float) Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'paid')
            ->sum('amount_paid');
    }
}
