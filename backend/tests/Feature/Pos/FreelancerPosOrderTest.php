<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\SubscriptionStatus;
use App\Models\PosProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreelancerPosOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_subscribed_freelancer_places_a_pending_order_visible_to_the_cashier(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 6, 'stock_qty' => 10]);

        $freelancer = User::factory()->freelancer()->create();
        Subscription::factory()->create([
            'member_id' => $freelancer->id,
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($freelancer);
        $place = $this->postJson('/api/freelancer/pos/orders', [
            'workspace_id' => $workspace->id,
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ]);
        $place->assertCreated()
            ->assertJsonPath('data.source', 'freelancer')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '12.00');

        // The order lands in the owner's/cashier's queue and can be settled there.
        Sanctum::actingAs($owner);
        $this->getJson('/api/pos/orders?status=pending')->assertOk()->assertJsonCount(1, 'data.orders');
        $this->postJson("/api/pos/orders/{$place->json('data.id')}/pay", ['method' => 'cash'])->assertOk();
        $this->assertSame(8, $product->fresh()->stock_qty);
    }

    public function test_freelancer_without_active_subscription_cannot_order(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        $freelancer = User::factory()->freelancer()->create();

        Sanctum::actingAs($freelancer);
        $this->postJson('/api/freelancer/pos/orders', [
            'workspace_id' => $workspace->id,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(403);
    }
}
