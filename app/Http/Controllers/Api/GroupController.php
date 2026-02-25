<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Group::with('members.user')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required|string',
            'type'=>'required|in:contribution,rounds,shared',
            'contribution_amount'=>'nullable|numeric',
            'frequency'=>'nullable|in:weekly,monthly,bi-monthly,yearly',
            'interest_rate'=>'nullable|numeric',
        ]);

        $group = Group::create(array_merge($request->all(), ['created_by'=>$request->user()->id]));
        GroupMember::create(['user_id'=>$request->user()->id,'group_id'=>$group->id,'role'=>'admin']);

        return response()->json($group);
    }

    public function join(Request $request)
    {
        $request->validate(['group_id'=>'required|exists:groups,id']);
        $alreadyMember = GroupMember::where('user_id',$request->user()->id)
            ->where('group_id',$request->group_id)
            ->exists();
        if($alreadyMember) return response()->json(['message'=>'Already a member'],400);

        $member = GroupMember::create(['user_id'=>$request->user()->id,'group_id'=>$request->group_id,'role'=>'member']);
        return response()->json($member);
    }
}