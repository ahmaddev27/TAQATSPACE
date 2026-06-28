<?php

declare(strict_types=1);

namespace Tests\Feature\Internet;

use App\Enums\SubscriptionStatus;
use App\Jobs\SendBroadcastMessage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InternetAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Provisioning internet access generates and stores credentials (password
 * encrypted) and only queues delivery on channels the workspace can send on.
 */
class InternetAccessTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): Subscription
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();

        return Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_provision_generates_and_stores_encrypted_credentials(): void
    {
        Bus::fake();
        $subscription = $this->subscription();

        $result = app(InternetAccessService::class)->provision($subscription);

        $this->assertNotEmpty($result['username']);
        $this->assertSame(8, strlen($result['password']));

        $subscription->refresh();
        $this->assertSame($result['username'], $subscription->internet_username);
        $this->assertNotNull($subscription->internet_provisioned_at);
        // The cast returns the plaintext back...
        $this->assertSame($result['password'], $subscription->internet_password_enc);

        // ...but the column itself is ciphered, never the plaintext.
        $raw = DB::table('subscriptions')->where('id', $subscription->id)->value('internet_password_enc');
        $this->assertNotSame($result['password'], $raw);
        $this->assertSame($result['password'], Crypt::decryptString($raw));
    }

    public function test_no_messages_are_queued_when_the_workspace_has_no_sender_accounts(): void
    {
        Bus::fake();
        $subscription = $this->subscription();

        $result = app(InternetAccessService::class)->provision($subscription);

        $this->assertFalse($result['sent_email']);
        $this->assertFalse($result['sent_sms']);
        Bus::assertNotDispatched(SendBroadcastMessage::class);
    }

    public function test_regenerating_keeps_the_username_but_changes_the_password(): void
    {
        Bus::fake();
        $subscription = $this->subscription();
        $service = app(InternetAccessService::class);

        $first = $service->provision($subscription);
        $second = $service->provision($subscription->refresh());

        $this->assertSame($first['username'], $second['username']);
        $this->assertNotSame($first['password'], $second['password']);
    }
}
