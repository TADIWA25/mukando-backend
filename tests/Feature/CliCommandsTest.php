<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CliCommandsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // mukando:create-user
    // -------------------------------------------------------------------------

    public function test_create_user_command_creates_user_successfully(): void
    {
        $this->artisan('mukando:create-user', [
            '--name' => 'Alice Doe',
            '--phone' => '263771000001',
            '--password' => 'secret1234',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'Alice Doe',
            'phone' => '263771000001',
        ]);
    }

    public function test_create_user_command_fails_when_phone_already_taken(): void
    {
        User::factory()->create(['phone' => '263771000002']);

        $this->artisan('mukando:create-user', [
            '--name' => 'Bob Smith',
            '--phone' => '263771000002',
            '--password' => 'secret1234',
        ])->assertFailed();
    }

    public function test_create_user_command_fails_with_short_password(): void
    {
        $this->artisan('mukando:create-user', [
            '--name' => 'Charlie',
            '--phone' => '263771000003',
            '--password' => 'short',
        ])->assertFailed();
    }

    // -------------------------------------------------------------------------
    // mukando:create-group
    // -------------------------------------------------------------------------

    public function test_create_group_command_creates_group_and_admin_membership(): void
    {
        $admin = User::factory()->create(['phone' => '263771000010']);

        $this->artisan('mukando:create-group', [
            '--name' => 'Test Group',
            '--type' => 'contribution',
            '--frequency' => 'monthly',
            '--target' => '1200',
            '--contribution' => '100',
            '--admin-phone' => '263771000010',
        ])->assertSuccessful();

        $this->assertDatabaseHas('groups', ['name' => 'Test Group', 'type' => 'contribution']);
        $group = Group::where('name', 'Test Group')->first();
        $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);
    }

    public function test_create_group_command_fails_for_unknown_admin(): void
    {
        $this->artisan('mukando:create-group', [
            '--name' => 'Ghost Group',
            '--type' => 'rounds',
            '--frequency' => 'weekly',
            '--target' => '500',
            '--contribution' => '50',
            '--admin-phone' => '263770000000',
        ])->assertFailed();

        $this->assertDatabaseMissing('groups', ['name' => 'Ghost Group']);
    }

    public function test_create_group_command_fails_with_invalid_type(): void
    {
        $admin = User::factory()->create(['phone' => '263771000011']);

        $this->artisan('mukando:create-group', [
            '--name' => 'Bad Type Group',
            '--type' => 'invalid',
            '--frequency' => 'monthly',
            '--target' => '1000',
            '--contribution' => '100',
            '--admin-phone' => '263771000011',
        ])->assertFailed();
    }

    // -------------------------------------------------------------------------
    // mukando:list-groups
    // -------------------------------------------------------------------------

    public function test_list_groups_command_outputs_all_groups(): void
    {
        User::factory()->create(['phone' => '263771000020', 'name' => 'Group Admin']);
        $admin = User::where('phone', '263771000020')->first();

        Group::create([
            'name' => 'Listed Group',
            'type' => 'contribution',
            'target_amount' => 1000,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->artisan('mukando:list-groups')->assertSuccessful();
    }

    public function test_list_groups_command_filters_by_user_phone(): void
    {
        $admin = User::factory()->create(['phone' => '263771000021']);
        $other = User::factory()->create(['phone' => '263771000022']);

        $group = Group::create([
            'name' => 'Admin Group',
            'type' => 'contribution',
            'target_amount' => 1000,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::create(['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->artisan('mukando:list-groups', ['--phone' => '263771000021'])
            ->assertSuccessful()
            ->expectsOutputToContain('Admin Group');

        $this->artisan('mukando:list-groups', ['--phone' => '263771000022'])
            ->assertSuccessful()
            ->expectsOutputToContain('No groups found.');
    }

    public function test_list_groups_command_fails_for_unknown_phone(): void
    {
        $this->artisan('mukando:list-groups', ['--phone' => '263770000099'])
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // mukando:group-summary
    // -------------------------------------------------------------------------

    public function test_group_summary_command_displays_group_info(): void
    {
        $admin = User::factory()->create(['phone' => '263771000030']);

        $group = Group::create([
            'name' => 'Summary Group',
            'type' => 'rounds',
            'target_amount' => 600,
            'contribution_amount' => 50,
            'frequency' => 'weekly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::create(['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->artisan("mukando:group-summary {$group->id}")
            ->assertSuccessful()
            ->expectsOutputToContain('Summary Group');
    }

    public function test_group_summary_command_fails_for_nonexistent_group(): void
    {
        $this->artisan('mukando:group-summary 99999')->assertFailed();
    }

    // -------------------------------------------------------------------------
    // mukando:record-contribution
    // -------------------------------------------------------------------------

    public function test_record_contribution_command_records_successfully(): void
    {
        $admin = User::factory()->create(['phone' => '263771000040']);

        $group = Group::create([
            'name' => 'Contribution Group',
            'type' => 'contribution',
            'target_amount' => 1200,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::create(['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->artisan('mukando:record-contribution', [
            '--group-id' => $group->id,
            '--phone' => '263771000040',
            '--amount' => '100',
        ])->assertSuccessful();

        $this->assertDatabaseHas('contributions', [
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'status' => 'paid',
        ]);
    }

    public function test_record_contribution_command_fails_for_nonmember(): void
    {
        $admin = User::factory()->create(['phone' => '263771000041']);
        $outsider = User::factory()->create(['phone' => '263771000042']);

        $group = Group::create([
            'name' => 'Members Only',
            'type' => 'contribution',
            'target_amount' => 1200,
            'contribution_amount' => 100,
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        GroupMember::create(['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->artisan('mukando:record-contribution', [
            '--group-id' => $group->id,
            '--phone' => '263771000042',
            '--amount' => '100',
        ])->assertFailed();
    }

    public function test_record_contribution_command_fails_for_unknown_group(): void
    {
        User::factory()->create(['phone' => '263771000043']);

        $this->artisan('mukando:record-contribution', [
            '--group-id' => '99999',
            '--phone' => '263771000043',
            '--amount' => '100',
        ])->assertFailed();
    }
}
