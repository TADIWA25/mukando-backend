<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupIndexInviteCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_invite_code_and_can_invite_for_member_groups(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $adminGroup = Group::query()->create([
            'name' => 'Admin Group',
            'type' => 'contribution',
            'target_amount' => 1200,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::query()->create([
            'group_id' => $adminGroup->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $memberGroup = Group::query()->create([
            'name' => 'Member Group',
            'type' => 'rounds',
            'target_amount' => 600,
            'contribution_amount' => 50,
            'frequency' => 'weekly',
            'status' => 'active',
            'created_by' => $member->id,
        ]);

        GroupMember::query()->create([
            'group_id' => $memberGroup->id,
            'user_id' => $admin->id,
            'role' => 'member',
        ]);

        $outsiderGroup = Group::query()->create([
            'name' => 'Outsider Group',
            'type' => 'shared',
            'target_amount' => 300,
            'contribution_amount' => 25,
            'frequency' => 'daily',
            'status' => 'active',
            'created_by' => $outsider->id,
        ]);

        GroupMember::query()->create([
            'group_id' => $outsiderGroup->id,
            'user_id' => $outsider->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/groups');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $adminGroup->id,
            'role' => 'admin',
            'invite_code' => $adminGroup->invite_code,
            'can_invite' => true,
        ]);
        $response->assertJsonFragment([
            'id' => $memberGroup->id,
            'role' => 'member',
            'invite_code' => $memberGroup->invite_code,
            'can_invite' => false,
        ]);
        $response->assertJsonMissing([
            'id' => $outsiderGroup->id,
        ]);
    }
}
