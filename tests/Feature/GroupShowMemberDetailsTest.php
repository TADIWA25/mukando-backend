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

        $cycle = $group->cycles()->create([
            'cycle_number' => 1,
            'due_date' => now()->toDateString(),
            'status' => 'open',
        ]);

        Contribution::query()->create([
            'group_id' => $group->id,
            'cycle_id' => $cycle->id,
            'user_id' => $member->id,
            'amount_paid' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/groups/{$group->id}");

        $response->assertOk()->assertJsonPath('status', true);

        $members = collect($response->json('data.members'));
        $adminPayload = $members->firstWhere('user_id', $admin->id);
        $memberPayload = $members->firstWhere('user_id', $member->id);

        $this->assertNotNull($adminPayload);
        $this->assertNotNull($memberPayload);

        $this->assertSame($adminMembership->id, $adminPayload['id']);
        $this->assertSame($admin->phone, $adminPayload['phone']);
        $this->assertSame($adminMembership->created_at?->toDateTimeString(), $adminPayload['joined_at']);
        $this->assertFalse($adminPayload['paid_this_cycle']);

        $this->assertSame($memberMembership->id, $memberPayload['id']);
        $this->assertSame($member->phone, $memberPayload['phone']);
        $this->assertSame($memberMembership->created_at?->toDateTimeString(), $memberPayload['joined_at']);
        $this->assertTrue($memberPayload['paid_this_cycle']);
    }
}
