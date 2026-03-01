<?php

namespace Tests\Feature;

use App\Models\Contribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_amount_is_required_for_contribution_group_type(): void
    {
        $response = $this->postJson('/api/contributions', [
            'group_type' => 'contribution',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_amount']);
    }

    public function test_target_amount_is_prohibited_for_non_contribution_group_type(): void
    {
        $response = $this->postJson('/api/contributions', [
            'group_type' => 'savings',
            'target_amount' => 1200,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_amount']);
    }

    public function test_can_create_contribution_group_with_target_amount(): void
    {
        $response = $this->postJson('/api/contributions', [
            'group_type' => 'contribution',
            'target_amount' => 1200,
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'group_type' => 'contribution',
                'target_amount' => '1200.00',
            ]);
    }

    public function test_update_uses_existing_group_type_for_validation_when_missing_from_payload(): void
    {
        $contribution = Contribution::query()->create([
            'group_type' => 'contribution',
            'target_amount' => 1000,
        ]);

        $response = $this->putJson("/api/contributions/{$contribution->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_amount']);
    }
}
