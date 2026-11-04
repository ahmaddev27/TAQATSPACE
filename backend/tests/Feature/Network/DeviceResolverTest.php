<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\HotspotAccount;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Network\DeviceResolver;
use App\Services\Network\HotspotEventProcessor;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceResolverTest extends TestCase
{
    use RefreshDatabase;

    private Router $router;

    private int $uid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create();
    }

    private function subscriberWithAccount(Router $router, string $username): User
    {
        $subscription = Subscription::factory()->create(['workspace_id' => $router->workspace_id]);

        HotspotAccount::query()->withoutWorkspaceScope()->create([
            'workspace_id' => $router->workspace_id,
            'subscription_id' => $subscription->id,
            'username' => $username,
            'password_enc' => 'secret',
        ]);

        return $subscription->member;
    }

    private function event(string $mac, ?string $username, ?CarbonInterface $at = null): HotspotEvent
    {
        return HotspotEvent::create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->router->workspace_id,
            'event_uid' => 'b1-'.++$this->uid,
            'batch_id' => 'batch',
            'type' => 'login',
            'mac' => $mac,
            'ip' => '10.0.0.9',
            'username' => $username,
            'occurred_at' => $at ?? now(),
            'received_at' => $at ?? now(),
        ]);
    }

    private function devices(): Collection
    {
        return Device::query()->withoutWorkspaceScope()->orderBy('mac')->get();
    }

    public function test_known_username_with_new_mac_creates_a_linked_device(): void
    {
        $subscriber = $this->subscriberWithAccount($this->router, 'ali');

        $device = app(DeviceResolver::class)->resolve($this->router, 'aa:bb:cc:dd:ee:01', 'ali');

        $this->assertSame(DeviceStatus::Linked, $device->status);
        $this->assertSame($subscriber->id, $device->subscriber_id);
        $this->assertSame('AA:BB:CC:DD:EE:01', $device->mac);
    }

    public function test_unknown_username_creates_exactly_one_discovered_device(): void
    {
        $resolver = app(DeviceResolver::class);

        foreach (range(1, 4) as $_) {
            $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:02', null);
            $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:03', 'nobody');
        }

        $devices = $this->devices();
        $this->assertCount(2, $devices);
        $this->assertSame([DeviceStatus::Discovered], $devices->pluck('status')->unique()->all());
        $this->assertNull($devices->first()->subscriber_id);
    }

    public function test_mac_spelling_variants_produce_a_single_row(): void
    {
        $resolver = app(DeviceResolver::class);

        $resolver->resolve($this->router, 'aa:bb:cc:dd:ee:04', null);
        $resolver->resolve($this->router, 'AA-BB-CC-DD-EE-04', null);
        $resolver->resolve($this->router, 'aabb.ccdd.ee04', null);

        $this->assertCount(1, $this->devices());
    }

    public function test_invalid_mac_is_ignored(): void
    {
        $this->assertNull(app(DeviceResolver::class)->resolve($this->router, 'not-a-mac', 'ali'));
        $this->assertCount(0, $this->devices());
    }

    public function test_discovered_device_is_linked_when_it_appears_with_a_known_username(): void
    {
        $subscriber = $this->subscriberWithAccount($this->router, 'ali');
        $resolver = app(DeviceResolver::class);

        $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:05', null);
        $device = $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:05', 'ali');

        $this->assertSame(DeviceStatus::Linked, $device->status);
        $this->assertSame($subscriber->id, $device->subscriber_id);
        $this->assertCount(1, $this->devices());
    }

    public function test_linked_device_keeps_its_owner(): void
    {
        $first = $this->subscriberWithAccount($this->router, 'ali');
        $this->subscriberWithAccount($this->router, 'sara');
        $resolver = app(DeviceResolver::class);

        $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:06', 'ali');
        $device = $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:06', 'sara');

        $this->assertSame($first->id, $device->subscriber_id);
    }

    public function test_blocked_device_stays_blocked(): void
    {
        $this->subscriberWithAccount($this->router, 'ali');
        $resolver = app(DeviceResolver::class);
        $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:07', null);
        Device::query()->withoutWorkspaceScope()->update(['status' => DeviceStatus::Blocked->value]);

        $device = $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:07', 'ali');

        $this->assertSame(DeviceStatus::Blocked, $device->status);
        $this->assertNull($device->subscriber_id);
    }

    public function test_randomized_mac_is_flagged(): void
    {
        $resolver = app(DeviceResolver::class);

        $random = $resolver->resolve($this->router, 'DA:A1:19:00:00:01', null);
        $fixed = $resolver->resolve($this->router, 'AC:A1:19:00:00:01', null);

        $this->assertTrue($random->is_randomized_mac);
        $this->assertFalse($fixed->is_randomized_mac);
    }

    public function test_a_subscriber_of_another_workspace_is_never_linked(): void
    {
        $other = Router::factory()->create();
        $this->subscriberWithAccount($other, 'ali');

        $device = app(DeviceResolver::class)->resolve($this->router, 'AA:BB:CC:DD:EE:08', 'ali');

        $this->assertSame(DeviceStatus::Discovered, $device->status);
        $this->assertNull($device->subscriber_id);
    }

    public function test_the_same_mac_in_two_workspaces_gets_two_devices(): void
    {
        $other = Router::factory()->create();
        $resolver = app(DeviceResolver::class);

        $resolver->resolve($this->router, 'AA:BB:CC:DD:EE:09', null);
        $resolver->resolve($other, 'AA:BB:CC:DD:EE:09', null);

        $this->assertCount(2, $this->devices());
    }

    public function test_seen_window_widens_but_never_shrinks(): void
    {
        $resolver = app(DeviceResolver::class);
        $mid = now()->subHours(2)->startOfSecond();
        $this->event('AA:BB:CC:DD:EE:10', null, $mid);
        $resolver->resolveBatch($this->router, HotspotEvent::all());

        HotspotEvent::query()->delete();
        $this->event('AA:BB:CC:DD:EE:10', null, $mid->copy()->subHour());
        $this->event('AA:BB:CC:DD:EE:10', null, $mid->copy()->addHour());
        $resolver->resolveBatch($this->router, HotspotEvent::all());

        $device = $this->devices()->sole();
        $this->assertTrue($device->first_seen_at->equalTo($mid->copy()->subHour()));
        $this->assertTrue($device->last_seen_at->equalTo($mid->copy()->addHour()));
    }

    public function test_a_500_event_batch_uses_a_constant_number_of_queries(): void
    {
        $this->subscriberWithAccount($this->router, 'ali');
        $events = collect(range(1, 500))->map(fn (int $i): HotspotEvent => $this->event(
            sprintf('AC:00:00:00:%02X:%02X', intdiv($i, 256), $i % 256),
            $i % 2 === 0 ? 'ali' : null,
        ));

        DB::enableQueryLog();
        app(DeviceResolver::class)->resolveBatch($this->router, $events);
        $first = count(DB::getQueryLog());
        DB::flushQueryLog();
        app(DeviceResolver::class)->resolveBatch($this->router, $events);
        $second = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(5, $first);
        $this->assertLessThanOrEqual(5, $second);
        $this->assertCount(500, $this->devices());
        $this->assertSame(250, $this->devices()->where('status', DeviceStatus::Linked)->count());
    }

    public function test_the_event_processor_records_devices(): void
    {
        $subscriber = $this->subscriberWithAccount($this->router, 'ali');
        $this->event('aa:bb:cc:dd:ee:11', 'ali');
        $this->event('AA:BB:CC:DD:EE:12', null);

        app(HotspotEventProcessor::class)->process($this->router->id);

        $devices = $this->devices()->keyBy('mac');
        $this->assertSame($subscriber->id, $devices['AA:BB:CC:DD:EE:11']->subscriber_id);
        $this->assertSame(DeviceStatus::Discovered, $devices['AA:BB:CC:DD:EE:12']->status);
    }
}
