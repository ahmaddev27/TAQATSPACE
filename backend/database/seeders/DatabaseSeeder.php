<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\UserRole;
use App\Enums\WorkspaceStatus;
use App\Models\Announcement;
use App\Models\BookingRequest;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Review;
use App\Models\SeatTypePrice;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SeatService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    private int $invoiceCounter = 1000;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRoles();

            $this->seedAdmin();
            $freelancers = $this->seedFreelancers(20);
            $activeWorkspaces = $this->seedOwnersAndWorkspaces();

            $subscriptions = $this->seedSubscriptions($freelancers, $activeWorkspaces);
            $this->seedInvoices($subscriptions);
            $this->seedBookingRequests($freelancers, $activeWorkspaces);
            $this->seedMessages($activeWorkspaces);
            $this->seedAnnouncements($activeWorkspaces);
            $this->seedReviews($subscriptions, $activeWorkspaces);
            $this->seedSiteContent();
        });

        $this->command?->info('Seeded admin: admin@taqat.space (password: password)');
    }

    private function seedRoles(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    private function seedAdmin(): User
    {
        $admin = User::factory()->admin()->create([
            'name' => 'مشرف المنصّة',
            'email' => 'admin@taqat.space',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    /**
     * @return Collection<int, User>
     */
    private function seedFreelancers(int $count): Collection
    {
        return User::factory()->freelancer()->count($count)->create()
            ->each(fn (User $user) => $user->assignRole(UserRole::Freelancer->value));
    }

    /**
     * Five owners, each with exactly one workspace (3 active, 1 pending, 1 suspended),
     * plus seats and internet packages. Returns the active workspaces only.
     *
     * @return Collection<int, Workspace>
     */
    private function seedOwnersAndWorkspaces(): Collection
    {
        $statuses = [
            WorkspaceStatus::Active,
            WorkspaceStatus::Active,
            WorkspaceStatus::Active,
            WorkspaceStatus::Pending,
            WorkspaceStatus::Suspended,
        ];

        $active = collect();

        foreach ($statuses as $i => $status) {
            $owner = User::factory()->owner()->create();
            $owner->assignRole(UserRole::WorkspaceOwner->value);

            $location = GazaData::LOCATIONS[$i % count(GazaData::LOCATIONS)];
            $totalSeats = random_int(10, 20);

            $workspace = Workspace::factory()->create([
                'owner_id' => $owner->id,
                'name' => GazaData::WORKSPACE_NAMES[$i % count(GazaData::WORKSPACE_NAMES)],
                'city' => $location['city'],
                'address' => $location['area'].'، '.$location['city'],
                'total_seats' => $totalSeats,
                'status' => $status,
            ]);

            // Seat types are the single source of truth for capacity: define
            // the per-type pricing/capacity, then derive the physical seats from
            // it so seeded data matches the runtime reconciliation behaviour.
            $this->seedSeatTypePrices($workspace);
            app(SeatService::class)->syncSeatsToCapacity($workspace);
            InternetPackage::factory()->count(random_int(2, 3))->create([
                'workspace_id' => $workspace->id,
            ]);

            if ($status === WorkspaceStatus::Active) {
                $active->push($workspace);
            }
        }

        return $active;
    }

    /**
     * One pricing row per seat type, derived from the workspace base price.
     * Flexible is the cheapest (hot-desk), fixed sits at the base, and a private
     * office carries a premium. Capacities split the workspace's total seats.
     */
    private function seedSeatTypePrices(Workspace $workspace): void
    {
        $base = (float) $workspace->price_per_month;
        $total = (int) $workspace->total_seats;

        $flexibleCapacity = (int) round($total * 0.5);
        $privateCapacity = (int) round($total * 0.2);
        $fixedCapacity = max(0, $total - $flexibleCapacity - $privateCapacity);

        $rows = [
            [
                'type' => SeatType::Flexible->value,
                'price_monthly' => round($base * 0.5, 2),
                'price_daily' => round($base / 22, 2),
                'capacity' => $flexibleCapacity,
            ],
            [
                'type' => SeatType::Fixed->value,
                'price_monthly' => round($base, 2),
                'price_daily' => round($base / 18, 2),
                'capacity' => $fixedCapacity,
            ],
            [
                'type' => SeatType::PrivateOffice->value,
                'price_monthly' => round($base * 1.6, 2),
                'price_daily' => round($base * 1.6 / 18, 2),
                'capacity' => $privateCapacity,
            ],
        ];

        foreach ($rows as $row) {
            SeatTypePrice::query()->create([
                'workspace_id' => $workspace->id,
                'type' => $row['type'],
                'price_monthly' => $row['price_monthly'],
                'price_daily' => $row['price_daily'],
                'capacity' => $row['capacity'],
                'enabled' => true,
            ]);
        }
    }

    /**
     * One active subscription per freelancer (seat assigned), plus a set of
     * historical (expired/cancelled) subscriptions for realistic volume.
     *
     * @param  Collection<int, User>  $freelancers
     * @param  Collection<int, Workspace>  $activeWorkspaces
     * @return Collection<int, Subscription>
     */
    private function seedSubscriptions(Collection $freelancers, Collection $activeWorkspaces): Collection
    {
        $subscriptions = collect();

        foreach ($freelancers as $i => $freelancer) {
            $workspace = $activeWorkspaces[$i % $activeWorkspaces->count()];
            $seat = $workspace->seats()
                ->where('status', SeatStatus::Available->value)
                ->whereNull('assigned_member_id')
                ->first();

            $subscription = Subscription::factory()->create([
                'member_id' => $freelancer->id,
                'workspace_id' => $workspace->id,
                'seat_id' => $seat?->id,
                'monthly_price' => $workspace->price_per_month,
            ]);

            $seat?->update([
                'status' => SeatStatus::Occupied,
                'assigned_member_id' => $freelancer->id,
            ]);

            $subscriptions->push($subscription);
        }

        // Historical subscriptions (members who have come and gone).
        for ($i = 0; $i < 30; $i++) {
            $state = fake()->boolean() ? 'expired' : 'cancelled';
            $workspace = $activeWorkspaces->random();

            $subscriptions->push(
                Subscription::factory()->{$state}()->create([
                    'member_id' => $freelancers->random()->id,
                    'workspace_id' => $workspace->id,
                    'seat_id' => null,
                    'monthly_price' => $workspace->price_per_month,
                ])
            );
        }

        return $subscriptions;
    }

    /**
     * Three months of invoice history per subscription, mixed statuses.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function seedInvoices(Collection $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
                $dueDate = Carbon::now()->startOfMonth()->subMonths($monthsAgo)->addDays(14);

                [$status, $paidAt] = $this->invoiceStatusFor($monthsAgo, $dueDate);

                Invoice::factory()->create([
                    'subscription_id' => $subscription->id,
                    'amount' => $subscription->monthly_price,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'paid_at' => $paidAt,
                    'invoice_number' => sprintf('TAQAT-%s-%04d', date('Y'), $this->invoiceCounter++),
                ]);
            }
        }
    }

    /**
     * @return array{0: InvoiceStatus, 1: ?Carbon}
     */
    private function invoiceStatusFor(int $monthsAgo, Carbon $dueDate): array
    {
        if ($monthsAgo === 0) {
            return [InvoiceStatus::Pending, null];
        }

        // Older invoices are mostly paid, occasionally overdue.
        if (fake()->boolean(80)) {
            return [InvoiceStatus::Paid, (clone $dueDate)->subDays(random_int(0, 10))];
        }

        return [InvoiceStatus::Overdue, null];
    }

    /**
     * 10 pending, 5 approved, 3 rejected booking requests.
     *
     * @param  Collection<int, User>  $freelancers
     * @param  Collection<int, Workspace>  $activeWorkspaces
     */
    private function seedBookingRequests(Collection $freelancers, Collection $activeWorkspaces): void
    {
        $make = function (string $state, int $count) use ($freelancers, $activeWorkspaces): void {
            for ($i = 0; $i < $count; $i++) {
                $workspace = $activeWorkspaces->random();
                $factory = BookingRequest::factory();

                $factory = match ($state) {
                    'approved' => $factory->approved($workspace->owner_id),
                    'rejected' => $factory->rejected($workspace->owner_id),
                    default => $factory,
                };

                $factory->create([
                    'member_id' => $freelancers->random()->id,
                    'workspace_id' => $workspace->id,
                ]);
            }
        };

        $make('pending', 10);
        $make('approved', 5);
        $make('rejected', 3);
    }

    /**
     * Per active workspace: 20 direct messages (owner → members) + 5 broadcasts.
     *
     * @param  Collection<int, Workspace>  $activeWorkspaces
     */
    private function seedMessages(Collection $activeWorkspaces): void
    {
        foreach ($activeWorkspaces as $workspace) {
            $memberIds = Subscription::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotNull('seat_id')
                ->pluck('member_id')
                ->all();

            if ($memberIds === []) {
                continue;
            }

            for ($i = 0; $i < 20; $i++) {
                Message::factory()->create([
                    'sender_id' => $workspace->owner_id,
                    'receiver_id' => Arr::random($memberIds),
                    'workspace_id' => $workspace->id,
                ]);
            }

            Message::factory()->broadcast()->count(5)->create([
                'sender_id' => $workspace->owner_id,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * A few published announcements per active workspace.
     *
     * @param  Collection<int, Workspace>  $activeWorkspaces
     */
    private function seedAnnouncements(Collection $activeWorkspaces): void
    {
        foreach ($activeWorkspaces as $workspace) {
            Announcement::factory()->count(3)->create([
                'workspace_id' => $workspace->id,
                'created_by' => $workspace->owner_id,
            ]);
        }
    }

    /**
     * 15 reviews across active workspaces (one per member/workspace pair),
     * then recompute each workspace's denormalized avg_rating.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     * @param  Collection<int, Workspace>  $activeWorkspaces
     */
    private function seedReviews(Collection $subscriptions, Collection $activeWorkspaces): void
    {
        $pairs = $subscriptions
            ->whereNotNull('seat_id')
            ->unique(fn (Subscription $s) => $s->member_id.'|'.$s->workspace_id)
            ->take(15);

        foreach ($pairs as $subscription) {
            Review::factory()->create([
                'member_id' => $subscription->member_id,
                'workspace_id' => $subscription->workspace_id,
            ]);
        }

        foreach ($activeWorkspaces as $workspace) {
            $average = Review::query()->where('workspace_id', $workspace->id)->avg('rating');

            if ($average !== null) {
                $workspace->update(['avg_rating' => round((float) $average, 2)]);
            }
        }
    }

    /**
     * Placeholder contact details so the public footer renders something before
     * a Super Admin fills in the real values. Other CMS keys (faq/about/
     * how_it_works) rely on the frontend's i18n fallbacks until edited.
     */
    private function seedSiteContent(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'site'],
            ['value' => [
                'contact_email' => 'info@taqat.space',
                'contact_phone' => '+970 59 000 0000',
                'whatsapp' => '+970 59 000 0000',
                'address' => ['ar' => 'غزة، فلسطين', 'en' => 'Gaza, Palestine'],
                'social' => [
                    'facebook' => null,
                    'instagram' => null,
                    'twitter' => null,
                    'linkedin' => null,
                ],
            ]],
        );
    }
}
