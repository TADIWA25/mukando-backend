<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupShowMemberDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_group_member_details_for_web_app_query(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Savings Circle',
            'type' => 'contribution',
            'target_amount' => 1000,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $adminMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $memberMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        Contribution::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'amount_paid' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // add a second member whose contribution is pending but with a non-zero amount
        $pendingUser = User::factory()->create();
        $pendingMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $pendingUser->id,
            'role' => 'member',
        ]);
        Contribution::query()->create([
            'group_id' => $group->id,
            'user_id' => $pendingUser->id,
            'amount_paid' => 50,
            'status' => 'pending',
        ]);

        // third member with a contribution record
        $thirdUser = User::factory()->create();
        $thirdMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $thirdUser->id,
            'role' => 'member',
        ]);
        Contribution::query()->create([
            'group_id' => $group->id,
            'user_id' => $thirdUser->id,
            'amount_paid' => 25,
            'status' => 'paid',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/groups/{$group->id}");

        $response->assertOk()->assertJsonPath('status', true);

        $members = collect($response->json('data.members'));
        $adminPayload = $members->firstWhere('user_id', $admin->id);
        $memberPayload = $members->firstWhere('user_id', $member->id);

        $this->assertNotNull($adminPayload);
        $this->assertNotNull($memberPayload);
        $pendingPayload = $members->firstWhere('user_id', $pendingUser->id);
        $this->assertNotNull($pendingPayload);
        $thirdPayload = $members->firstWhere('user_id', $thirdUser->id);
        $this->assertNotNull($thirdPayload);

        $this->assertSame($adminMembership->id, $adminPayload['id']);
        $this->assertSame($admin->phone, $adminPayload['phone']);
        $this->assertSame($adminMembership->created_at?->toDateTimeString(), $adminPayload['joined_at']);
        $this->assertFalse($adminPayload['paid_this_cycle']);
        $this->assertSame(0, $adminPayload['contribution_amount']);

        $this->assertSame($memberMembership->id, $memberPayload['id']);
        $this->assertSame($member->phone, $memberPayload['phone']);
        $this->assertSame($memberMembership->created_at?->toDateTimeString(), $memberPayload['joined_at']);
        $this->assertTrue($memberPayload['paid_this_cycle']);
        $this->assertSame(100, $memberPayload['contribution_amount']);

        $this->assertSame($pendingMembership->id, $pendingPayload['id']);
        $this->assertSame($pendingUser->phone, $pendingPayload['phone']);
        $this->assertSame($pendingMembership->created_at?->toDateTimeString(), $pendingPayload['joined_at']);
        // although status field was pending, amount_paid > 0 should mark them paid
        $this->assertTrue($pendingPayload['paid_this_cycle']);
        $this->assertSame(50, $pendingPayload['contribution_amount']);

        $this->assertSame($thirdMembership->id, $thirdPayload['id']);
        $this->assertSame($thirdUser->phone, $thirdPayload['phone']);
        $this->assertSame($thirdMembership->created_at?->toDateTimeString(), $thirdPayload['joined_at']);
        $this->assertTrue($thirdPayload['paid_this_cycle']);
        $this->assertSame(25, $thirdPayload['contribution_amount']);
    }
}
