<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\HotspotAccount;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Support\Network\MacAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Links network identity to business identity: a hotspot username belongs to
 * a subscriber, a MAC to a `devices` row. Unknown MACs are recorded as
 * `discovered` and linked later, when they show up under a known username.
 *
 * Works on a whole batch with a constant number of queries: one read of the
 * existing devices, one read of the account owners, one upsert per chunk.
 */
class DeviceResolver
{
    private const WRITE_CHUNK = 500;

    private const UPSERT_UPDATE_COLUMNS = ['subscriber_id', 'status', 'first_seen_at', 'last_seen_at'];

    /** @return Device|null null when `$mac` is not a valid MAC address */
    public function resolve(Router $router, string $mac, ?string $username): ?Device
    {
        $normalized = MacAddress::normalize($mac);

        if ($normalized === null) {
            return null;
        }

        $now = now();

        return $this->persist($router, [$normalized => ['username' => $username, 'first' => $now, 'last' => $now]])->get($normalized);
    }

    /**
     * @param  Collection<int, HotspotEvent>  $events  events of ONE router
     * @return Collection<string, Device> keyed by normalized MAC
     */
    public function resolveBatch(Router $router, Collection $events): Collection
    {
        return $this->persist($router, $this->sightings($events));
    }

    /**
     * One entry per MAC: when it was first/last seen in the batch and the
     * latest username it presented.
     *
     * @param  Collection<int, HotspotEvent>  $events
     * @return array<string, array{username: ?string, first: CarbonInterface, last: CarbonInterface}>
     */
    private function sightings(Collection $events): array
    {
        $sightings = [];
        $ordered = $events->sortBy(fn (HotspotEvent $event): array => [$event->occurred_at->getTimestamp(), $event->id]);

        foreach ($ordered as $event) {
            $mac = MacAddress::normalize($event->mac);

            if ($mac === null) {
                continue;
            }

            $sightings[$mac] = [
                'username' => $event->username ?? ($sightings[$mac]['username'] ?? null),
                'first' => $sightings[$mac]['first'] ?? $event->occurred_at,
                'last' => $event->occurred_at,
            ];
        }

        return $sightings;
    }

    /**
     * @param  array<string, array{username: ?string, first: CarbonInterface, last: CarbonInterface}>  $sightings
     * @return Collection<string, Device>
     */
    private function persist(Router $router, array $sightings): Collection
    {
        if ($sightings === []) {
            return collect();
        }

        $macs = array_keys($sightings);
        $existing = $this->devicesFor($router->workspace_id, $macs);
        $owners = $this->subscribersByUsername($router->workspace_id, array_filter(array_column($sightings, 'username')));

        $rows = [];
        foreach ($sightings as $mac => $sighting) {
            $subscriberId = $sighting['username'] === null ? null : ($owners[$sighting['username']] ?? null);
            $row = $this->mergedRow($router->workspace_id, $mac, $sighting, $subscriberId, $existing->get($mac));

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
            Device::query()->withoutWorkspaceScope()->upsert($chunk, ['workspace_id', 'mac'], self::UPSERT_UPDATE_COLUMNS);
        }

        return $rows === [] ? $existing : $this->devicesFor($router->workspace_id, $macs);
    }

    /**
     * The row to write, or null when nothing about the device changed.
     * Only a `discovered` device gets linked: a linked one keeps its owner and
     * a blocked one keeps its status (managing those is a separate task).
     *
     * @param  array{username: ?string, first: CarbonInterface, last: CarbonInterface}  $sighting
     * @return array<string, mixed>|null
     */
    private function mergedRow(string $workspaceId, string $mac, array $sighting, ?string $subscriberId, ?Device $device): ?array
    {
        if ($device === null) {
            return $this->newRow($workspaceId, $mac, $sighting, $subscriberId);
        }

        $first = $sighting['first']->lessThan($device->first_seen_at) ? $sighting['first'] : $device->first_seen_at;
        $last = $sighting['last']->greaterThan($device->last_seen_at) ? $sighting['last'] : $device->last_seen_at;
        $links = $device->status === DeviceStatus::Discovered && $subscriberId !== null;

        if (! $links && $first->equalTo($device->first_seen_at) && $last->equalTo($device->last_seen_at)) {
            return null;
        }

        return [
            ...$this->identityColumns($device),
            'subscriber_id' => $links ? $subscriberId : $device->subscriber_id,
            'status' => ($links ? DeviceStatus::Linked : $device->status)->value,
            'first_seen_at' => $first,
            'last_seen_at' => $last,
        ];
    }

    /**
     * @param  array{username: ?string, first: CarbonInterface, last: CarbonInterface}  $sighting
     * @return array<string, mixed>
     */
    private function newRow(string $workspaceId, string $mac, array $sighting, ?string $subscriberId): array
    {
        return [
            'workspace_id' => $workspaceId,
            'mac' => $mac,
            'label' => null,
            'vendor' => null,
            'device_type' => null,
            'is_randomized_mac' => MacAddress::isRandomized($mac),
            'subscriber_id' => $subscriberId,
            'status' => ($subscriberId === null ? DeviceStatus::Discovered : DeviceStatus::Linked)->value,
            'first_seen_at' => $sighting['first'],
            'last_seen_at' => $sighting['last'],
        ];
    }

    /** @return array<string, mixed> columns an upsert must carry but never changes */
    private function identityColumns(Device $device): array
    {
        return [
            'workspace_id' => $device->workspace_id,
            'mac' => $device->mac,
            'label' => $device->label,
            'vendor' => $device->vendor,
            'device_type' => $device->device_type,
            'is_randomized_mac' => $device->is_randomized_mac,
        ];
    }

    /**
     * @param  list<string>  $macs
     * @return Collection<string, Device>
     */
    private function devicesFor(string $workspaceId, array $macs): Collection
    {
        return Device::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereIn('mac', $macs)
            ->get()
            ->keyBy('mac');
    }

    /**
     * Username => subscriber id, limited to the router's own workspace so a
     * same-named account elsewhere can never claim the device.
     *
     * @param  array<int, string>  $usernames
     * @return array<string, string>
     */
    private function subscribersByUsername(string $workspaceId, array $usernames): array
    {
        if ($usernames === []) {
            return [];
        }

        return HotspotAccount::query()
            ->withoutWorkspaceScope()
            ->join('subscriptions', 'subscriptions.id', '=', 'hotspot_accounts.subscription_id')
            ->where('hotspot_accounts.workspace_id', $workspaceId)
            ->where('subscriptions.workspace_id', $workspaceId)
            ->whereIn('hotspot_accounts.username', array_values(array_unique($usernames)))
            ->pluck('subscriptions.member_id', 'hotspot_accounts.username')
            ->all();
    }
}
