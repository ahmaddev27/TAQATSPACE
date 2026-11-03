<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Console\Commands\ExpireRouterCommands;
use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\Workspace;
use App\Services\Router\RouterCommandService;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouterCommandQueueTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'ABCD2345';

    private Router $router;

    private string $key;

    private int $batch = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Syncs here are driven in tight loops; the 12/min router limit is covered by HotspotSyncTest.
        $this->withoutMiddleware(ThrottleRequests::class);

        $workspace = Workspace::factory()->create(['code' => self::CODE]);
        $this->key = RouterKeyHasher::generate();
        $this->router = Router::factory()->withKey($this->key)->create(['workspace_id' => $workspace->id]);
    }

    private function service(): RouterCommandService
    {
        return app(RouterCommandService::class);
    }

    /** @param list<string> $acks A lines without the tag, e.g. "id|ok" */
    private function sync(array $acks = [], string $mode = 'full'): string
    {
        $this->batch++;
        $body = "H|batch{$this->batch}|{$this->batch}|boot7|2026-11-03 12:30:00|{$mode}|nonce{$this->batch}|1.0.0|SN1|7.14.3\n";
        $body .= implode('', array_map(static fn (string $ack): string => "A|{$ack}\n", $acks));

        $response = $this->call('POST', '/api/v1/hotspot/'.self::CODE.'/sync', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $this->key,
            'HTTP_ACCEPT' => 'text/plain',
        ], $body);
        $response->assertOk();

        return $response->getContent();
    }

    /** @return list<string> the C lines of a reply */
    private function commandLines(string $reply): array
    {
        return array_values(array_filter(explode("\n", $reply), static fn (string $line): bool => str_starts_with($line, 'C|')));
    }

    private function upsertUser(string $name = 'alice'): RouterCommand
    {
        return $this->service()->enqueue($this->router, RouterCommandVerb::UpsertUser, ['username' => $name, 'password' => 'p4ss', 'profile' => 'visitor']);
    }

    public function test_a_command_is_delivered_in_the_next_sync_and_acknowledged_by_the_following_one(): void
    {
        $command = $this->upsertUser();

        $this->assertSame(RouterCommandStatus::Requested, $command->refresh()->status);

        $lines = $this->commandLines($this->sync());

        $this->assertSame(["C|{$command->id}|upsert-user|alice|p4ss|visitor"], $lines);
        $command->refresh();
        $this->assertSame(RouterCommandStatus::Delivered, $command->status);
        $this->assertSame(1, $command->attempts);
        $this->assertNotNull($command->delivered_at);

        $this->assertSame([], $this->commandLines($this->sync(["{$command->id}|ok"])));
        $command->refresh();
        $this->assertSame(RouterCommandStatus::Acknowledged, $command->status);
        $this->assertNotNull($command->acknowledged_at);
    }

    public function test_an_acknowledged_command_is_never_sent_again(): void
    {
        $command = $this->upsertUser();
        $this->sync();
        $this->sync(["{$command->id}|ok"]);

        foreach (range(1, 5) as $ignored) {
            $this->assertSame([], $this->commandLines($this->sync()));
        }
    }

    public function test_an_unacknowledged_command_is_resent_after_three_syncs(): void
    {
        $command = $this->upsertUser();
        $this->assertCount(1, $this->commandLines($this->sync()));

        $this->assertSame([], $this->commandLines($this->sync()));
        $this->assertSame([], $this->commandLines($this->sync()));
        $this->assertCount(1, $this->commandLines($this->sync()));

        $command->refresh();
        $this->assertSame(2, $command->attempts);
        $this->assertSame(RouterCommandStatus::Delivered, $command->status);
    }

    public function test_a_command_is_given_up_on_after_the_maximum_number_of_sends(): void
    {
        $command = $this->upsertUser();

        foreach (range(1, 20) as $ignored) {
            $this->sync();
        }

        $command->refresh();
        $this->assertSame(RouterCommandStatus::Failed, $command->status);
        $this->assertSame(RouterCommandService::MAX_ATTEMPTS, $command->attempts);
        $this->assertSame(RouterCommandService::ERROR_UNACKNOWLEDGED, $command->error);
    }

    public function test_an_expired_command_is_not_sent_and_becomes_expired(): void
    {
        $command = $this->service()->enqueue($this->router, RouterCommandVerb::DisableUser, ['username' => 'bob'], 10);

        $this->travel(11)->minutes();

        $this->assertSame([], $this->commandLines($this->sync()));
        $this->artisan('router-commands:expire')->assertSuccessful();
        $this->assertSame(RouterCommandStatus::Expired, $command->refresh()->status);
    }

    public function test_expiry_also_covers_commands_already_delivered(): void
    {
        $command = $this->service()->enqueue($this->router, RouterCommandVerb::DisableUser, ['username' => 'bob'], 10);
        $this->sync();

        $this->travel(11)->minutes();
        $this->sync(["{$command->id}|ok"]);

        $this->assertSame(RouterCommandStatus::Delivered, $command->refresh()->status);
        $this->artisan(ExpireRouterCommands::class)->assertSuccessful();
        $this->assertSame(RouterCommandStatus::Expired, $command->refresh()->status);
    }

    public function test_a_verb_outside_the_allowlist_is_rejected(): void
    {
        try {
            $this->service()->enqueue($this->router, 'reboot', []);
            $this->fail('expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('verb', $e->errors());
        }

        $this->assertSame(0, RouterCommand::query()->withoutWorkspaceScope()->count());
    }

    /** @return array<string, array{0: RouterCommandVerb, 1: array<string, mixed>}> */
    public static function badPayloads(): array
    {
        return [
            'pipe in the user name' => [RouterCommandVerb::UpsertUser, ['username' => 'a|b', 'password' => 'x']],
            'line break in the user name' => [RouterCommandVerb::DisableUser, ['username' => "a\nC|x|set-key"]],
            'trailing line break' => [RouterCommandVerb::RemoveActive, ['username' => "alice\n"]],
            'pipe in the password' => [RouterCommandVerb::UpsertUser, ['username' => 'a', 'password' => 'x|y']],
            'password too long' => [RouterCommandVerb::UpsertUser, ['username' => 'a', 'password' => str_repeat('x', 65)]],
            'lower case mac' => [RouterCommandVerb::BlockMac, ['mac' => 'aa:bb:cc:dd:ee:01']],
            'short key' => [RouterCommandVerb::SetKey, ['key' => 'abc123']],
            'unknown script' => [RouterCommandVerb::UpdateScript, ['script' => 'cws-evil', 'sha256' => str_repeat('a', 64)]],
            'bad sha' => [RouterCommandVerb::UpdateScript, ['script' => 'cws-hooks', 'sha256' => 'xyz']],
            'rate limit with a space' => [RouterCommandVerb::UpsertProfile, ['name' => 'p', 'rate_limit' => '1M 2M']],
            'missing payload' => [RouterCommandVerb::BlockMac, []],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('badPayloads')]
    public function test_a_payload_that_breaks_the_verb_rules_is_rejected(RouterCommandVerb $verb, array $payload): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service()->enqueue($this->router, $verb, $payload);
        } finally {
            $this->assertSame(0, RouterCommand::query()->withoutWorkspaceScope()->count());
        }
    }

    public function test_every_verb_builds_a_wire_line_from_a_valid_payload(): void
    {
        $sha = str_repeat('a', 64);
        $samples = [
            [RouterCommandVerb::UpsertUser, ['username' => 'a', 'password' => 'p'], 'upsert-user|a|p|'],
            [RouterCommandVerb::DisableUser, ['username' => 'a'], 'disable-user|a'],
            [RouterCommandVerb::RemoveActive, ['username' => 'a'], 'remove-active|a'],
            [RouterCommandVerb::BlockMac, ['mac' => 'AA:BB:CC:DD:EE:01'], 'block-mac|AA:BB:CC:DD:EE:01'],
            [RouterCommandVerb::UpsertProfile, ['name' => 'gold', 'rate_limit' => '5M/10M'], 'upsert-profile|gold|5M/10M'],
            [RouterCommandVerb::SetKey, ['key' => str_repeat('b', 64)], 'set-key|'.str_repeat('b', 64)],
            [RouterCommandVerb::UpdateScript, ['script' => 'cws-sync', 'sha256' => $sha], "update-script|cws-sync|{$sha}"],
        ];

        foreach ($samples as [$verb, $payload, $expected]) {
            $command = $this->service()->enqueue($this->router, $verb, $payload);

            $this->assertSame("C|{$command->id}|{$expected}", $this->service()->wireLine($command));
        }
    }

    public function test_a_duplicate_acknowledgement_does_not_change_the_status(): void
    {
        $command = $this->upsertUser();
        $this->sync();
        $this->sync(["{$command->id}|ok"]);
        $acknowledgedAt = $command->refresh()->acknowledged_at;

        $this->travel(5)->minutes();
        $this->sync(["{$command->id}|ok", "{$command->id}|failed"]);

        $command->refresh();
        $this->assertSame(RouterCommandStatus::Acknowledged, $command->status);
        $this->assertEquals($acknowledgedAt, $command->acknowledged_at);
        $this->assertNull($command->error);
    }

    public function test_a_rejection_marks_the_command_failed_with_the_reason(): void
    {
        $command = $this->upsertUser();
        $other = $this->upsertUser('carol');
        $this->sync();

        $this->sync(["{$command->id}|bad-args", "{$other->id}|ok"]);

        $this->assertSame(RouterCommandStatus::Failed, $command->refresh()->status);
        $this->assertSame('bad-args', $command->error);
        $this->assertSame(RouterCommandStatus::Acknowledged, $other->refresh()->status);
    }

    public function test_acks_for_unknown_foreign_or_undelivered_commands_are_ignored(): void
    {
        $foreignRouter = Router::factory()->create(['workspace_id' => $this->router->workspace_id]);
        $foreign = $this->service()->enqueue($foreignRouter, RouterCommandVerb::DisableUser, ['username' => 'x']);
        $queued = $this->upsertUser();

        $this->sync(["{$foreign->id}|ok", "{$queued->id}|ok", 'not-a-uuid|ok', '3f2b8c1e-0000-4000-8000-000000000000|ok']);

        $this->assertSame(RouterCommandStatus::Requested, $foreign->refresh()->status);
        $this->assertSame(RouterCommandStatus::Delivered, $queued->refresh()->status);
    }

    public function test_commands_are_delivered_oldest_first_and_only_to_their_own_router(): void
    {
        $otherRouter = Router::factory()->create(['workspace_id' => $this->router->workspace_id]);
        $this->service()->enqueue($otherRouter, RouterCommandVerb::DisableUser, ['username' => 'other']);
        $first = $this->upsertUser('first');
        $this->travel(1)->seconds();
        $second = $this->upsertUser('second');

        $lines = $this->commandLines($this->sync());

        $this->assertSame([$this->service()->wireLine($first), $this->service()->wireLine($second)], $lines);
    }

    public function test_the_reply_stays_within_four_kilobytes_and_the_rest_waits_in_order(): void
    {
        $commands = [];
        foreach (range(1, 40) as $index) {
            $commands[] = $this->service()->enqueue($this->router, RouterCommandVerb::SetKey, ['key' => str_repeat('a', 96)]);
            $this->travel(1)->seconds();
        }

        $reply = $this->sync();

        $this->assertLessThanOrEqual(4096, strlen($reply));
        $sent = $this->commandLines($reply);
        $this->assertNotEmpty($sent);
        $this->assertLessThan(40, count($sent));
        $deliveredIds = RouterCommand::query()->withoutWorkspaceScope()->where('status', 'delivered')->orderBy('created_at')->pluck('id')->all();
        $this->assertSame(array_map(static fn (RouterCommand $c): string => $c->id, array_slice($commands, 0, count($sent))), $deliveredIds);

        $this->assertNotEmpty($this->commandLines($this->sync()));
    }

    public function test_a_poll_only_router_only_receives_the_verbs_it_may_run(): void
    {
        $user = $this->upsertUser();
        $key = $this->service()->enqueue($this->router, RouterCommandVerb::SetKey, ['key' => str_repeat('c', 32)]);

        $lines = $this->commandLines($this->sync(mode: 'poll-only'));

        $this->assertSame([$this->service()->wireLine($key)], $lines);
        $this->assertSame(RouterCommandStatus::Requested, $user->refresh()->status);
        $this->assertSame([$this->service()->wireLine($user)], $this->commandLines($this->sync()));
    }

    public function test_a_retried_batch_gets_the_same_reply_and_does_not_advance_the_queue(): void
    {
        $command = $this->upsertUser();
        $body = "H|retry1|1|boot7|2026-11-03 12:30:00|full|nonceretry|1.0.0|SN1|7.14.3\n";
        $post = fn () => $this->call('POST', '/api/v1/hotspot/'.self::CODE.'/sync', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $this->key,
        ], $body)->getContent();

        $first = $post();
        $second = $post();

        $this->assertSame($first, $second);
        $this->assertSame(1, $command->refresh()->attempts);
        $this->assertSame(0, $command->sync_cycles);
    }

    public function test_the_payload_is_encrypted_at_rest(): void
    {
        $command = $this->upsertUser();

        $raw = (string) DB::table('router_commands')->where('id', $command->id)->value('payload');

        $this->assertStringNotContainsString('p4ss', $raw);
        $this->assertSame('p4ss', $command->refresh()->payload['password']);
    }
}
