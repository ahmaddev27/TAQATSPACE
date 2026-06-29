<?php

declare(strict_types=1);

namespace Tests\Feature\ExpensesResources;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The expense summary buckets months with MySQL's DATE_FORMAT, which
        // sqlite lacks — register an equivalent UDF so /index runs end-to-end.
        DB::connection()->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            static function (?string $date, string $format): ?string {
                if ($date === null) {
                    return null;
                }

                $php = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

                return date($php, (int) strtotime($date));
            },
            2,
        );
    }

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
    }

    public function test_index_returns_expenses_summary_and_meta(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Expense::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspace/expenses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'expenses' => [['id', 'title', 'category', 'amount', 'spent_on', 'notes']],
                    'summary' => ['total', 'this_month', 'by_category', 'by_month'],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ])
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_index_only_returns_the_owners_workspace_expenses(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        Expense::factory()->create(['workspace_id' => $workspace->id]);
        Expense::factory()->count(3)->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/expenses')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_store_creates_an_expense_and_returns_201(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $payload = [
            'title' => 'Office Rent',
            'category' => ExpenseCategory::Rent->value,
            'amount' => 1200.50,
            'spent_on' => '2026-02-01',
            'notes' => 'February rent',
        ];

        $this->postJson('/api/workspace/expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Office Rent')
            ->assertJsonPath('data.category', ExpenseCategory::Rent->value);

        $this->assertDatabaseHas('workspace_expenses', [
            'workspace_id' => $workspace->id,
            'title' => 'Office Rent',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/expenses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category', 'amount', 'spent_on']);
    }

    public function test_store_rejects_an_invalid_category(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/expenses', [
            'title' => 'X',
            'category' => 'not-a-category',
            'amount' => 10,
            'spent_on' => '2026-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['category']);
    }

    public function test_update_modifies_an_owned_expense(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Old',
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/expenses/{$expense->id}", [
            'title' => 'Updated',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 99,
            'spent_on' => '2026-03-03',
        ])->assertOk()->assertJsonPath('data.title', 'Updated');

        $this->assertDatabaseHas('workspace_expenses', [
            'id' => $expense->id,
            'title' => 'Updated',
        ]);
    }

    public function test_update_forbidden_for_foreign_expense(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        $foreign = Expense::factory()->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/expenses/{$foreign->id}", [
            'title' => 'Hacked',
            'category' => ExpenseCategory::Other->value,
            'amount' => 1,
            'spent_on' => '2026-01-01',
        ])->assertStatus(403);
    }

    public function test_destroy_deletes_an_owned_expense(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/expenses/{$expense->id}")->assertOk();

        $this->assertDatabaseMissing('workspace_expenses', ['id' => $expense->id]);
    }

    public function test_destroy_forbidden_for_foreign_expense(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        $foreign = Expense::factory()->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/expenses/{$foreign->id}")->assertStatus(403);
        $this->assertDatabaseHas('workspace_expenses', ['id' => $foreign->id]);
    }

    public function test_guest_cannot_access_expenses(): void
    {
        $this->getJson('/api/workspace/expenses')->assertUnauthorized();
    }

    public function test_non_owner_is_forbidden(): void
    {
        $freelancer = User::factory()->freelancer()->create();
        Sanctum::actingAs($freelancer);

        $this->getJson('/api/workspace/expenses')->assertStatus(403);
    }
}
