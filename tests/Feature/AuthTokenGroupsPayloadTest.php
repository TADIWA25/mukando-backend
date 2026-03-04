<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTokenGroupsPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_includes_groups_payload_with_token(): void
    {
        $user = User::factory()->create([
            'phone' => '263771111111',
            'password' => Hash::make('secret123'),
        ]);

        $group = Group::query()->create([
            'name' => 'Road Trip Fund',
            'type' => 'contribution',
            'target_amount' => 5000,
            'contribution_amount' => 500,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => '0771111111',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.name', $group->name)
            ->assertJsonPath('groups.0.role', 'admin')
            ->assertJsonPath('groups.0.can_invite', true)
            ->assertJsonStructure([
                'token',
                'user',
                'groups',
            ]);
    }

    public function test_register_response_contains_empty_groups_payload(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'phone' => '0772222222',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('groups', [])
            ->assertJsonStructure([
                'token',
                'user',
                'groups',
            ]);
    }
}
