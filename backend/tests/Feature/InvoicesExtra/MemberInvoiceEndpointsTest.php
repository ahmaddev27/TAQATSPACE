<?php

declare(strict_types=1);

namespace Tests\Feature\InvoicesExtra;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceReceiptSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberInvoiceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Subscription}
     */
    private function ownerAndMemberSubscription(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active,
        ]);

        return [$owner, $member, $subscription];
    }

    public function test_member_index_lists_only_their_own_invoices(): void
    {
        [, $member, $subscription] = $this->ownerAndMemberSubscription();
        Invoice::factory()->count(2)->create(['subscription_id' => $subscription->id]);

        // Another member's invoice must not leak in.
        $otherSub = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        Invoice::factory()->create(['subscription_id' => $otherSub->id]);

        Sanctum::actingAs($member);

        $this->getJson('/api/member/invoices')
            ->assertOk()
            ->assertJsonCount(2, 'data.invoices')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_member_show_404s_for_another_members_invoice(): void
    {
        [, $member] = $this->ownerAndMemberSubscription();
        $otherSub = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        $foreign = Invoice::factory()->create(['subscription_id' => $otherSub->id]);

        Sanctum::actingAs($member);

        $this->getJson("/api/member/invoices/{$foreign->id}")->assertNotFound();
    }

    public function test_member_upload_receipt_moves_invoice_under_review_and_notifies_owner(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$owner, $member, $subscription] = $this->ownerAndMemberSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending,
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/member/invoices/{$invoice->id}/receipt", [
            'receipt' => UploadedFile::fake()->image('proof.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::UnderReview->value);

        $this->assertSame(InvoiceStatus::UnderReview, $invoice->refresh()->status);
        Notification::assertSentTo($owner, InvoiceReceiptSubmittedNotification::class);
    }

    public function test_member_upload_receipt_422_when_invoice_already_paid(): void
    {
        Storage::fake('public');
        [, $member, $subscription] = $this->ownerAndMemberSubscription();
        $invoice = Invoice::factory()->paid()->create(['subscription_id' => $subscription->id]);

        Sanctum::actingAs($member);

        $this->postJson("/api/member/invoices/{$invoice->id}/receipt", [
            'receipt' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertStatus(422);
    }

    public function test_member_upload_receipt_validates_file_required(): void
    {
        [, $member, $subscription] = $this->ownerAndMemberSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending,
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/member/invoices/{$invoice->id}/receipt", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('receipt');
    }

    public function test_member_routes_reject_unauthenticated(): void
    {
        $this->getJson('/api/member/invoices')->assertUnauthorized();
    }
}
