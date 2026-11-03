<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Contracts\WorkspaceResetStep;
use App\Enums\SeatStatus;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\TwoFactorService;
use App\Services\Workspace\Reset\WorkspaceResetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WorkspaceResetTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/workspace/danger/reset';

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(bool $twoFactor = true, bool $verified = true): array
    {
        $owner = User::factory()->owner()->create();

        if ($twoFactor) {
            $owner->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();

            if ($verified) {
                app(TwoFactorService::class)->markVerified($owner);
            }
        }

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
    }

    /** Seeds one row of every operational kind for a workspace. */
    private function seedOperationalData(Workspace $workspace): void
    {
        $seat = Seat::factory()->create(['workspace_id' => $workspace->id, 'status' => SeatStatus::Occupied]);
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id, 'seat_id' => $seat->id]);
        $seat->update(['assigned_member_id' => $subscription->member_id]);
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'receipt_path' => "receipts/{$workspace->id}.jpg",
        ]);
        InvoicePayment::factory()->create(['invoice_id' => $invoice->id, 'receipt_path' => "receipts/{$workspace->id}-p.jpg"]);
        BookingRequest::factory()->create(['workspace_id' => $workspace->id]);
        Expense::factory()->create(['workspace_id' => $workspace->id]);
        PosOrder::query()->create([
            'workspace_id' => $workspace->id, 'order_number' => 'A-1', 'source' => 'cashier',
            'status' => 'new', 'subtotal' => 10, 'discount' => 0, 'total' => 10,
        ]);
    }

    /** @return array<string, int> */
    private function counts(Workspace $workspace): array
    {
        $id = $workspace->id;
        $subscriptionIds = Subscription::query()->where('workspace_id', $id)->pluck('id');

        return [
            'subscriptions' => Subscription::query()->where('workspace_id', $id)->count(),
            'invoices' => Invoice::query()->whereIn('subscription_id', $subscriptionIds)->count(),
            'bookings' => BookingRequest::query()->where('workspace_id', $id)->count(),
            'expenses' => Expense::query()->where('workspace_id', $id)->count(),
            'pos' => PosOrder::query()->where('workspace_id', $id)->count(),
            'occupied' => Seat::query()->where('workspace_id', $id)->where('status', SeatStatus::Occupied)->count(),
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function reset(User $owner, Workspace $workspace, array $overrides = [])
    {
        return $this->actingAs($owner, 'sanctum')->deleteJson(self::URL, $overrides + [
            'confirmation' => $workspace->slug,
            'reason' => 'Starting a new season',
        ]);
    }

    public function test_reset_deletes_operational_data_and_keeps_identity_and_configuration(): void
    {
        Storage::fake('public');
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $this->seedOperationalData($workspace);
        PosProduct::factory()->create(['workspace_id' => $workspace->id]);
        Storage::disk('public')->put("receipts/{$workspace->id}.jpg", 'x');
        $seatCount = Seat::query()->where('workspace_id', $workspace->id)->count();

        $this->reset($owner, $workspace)->assertOk();

        $this->assertSame(array_fill_keys(array_keys($this->counts($workspace)), 0), $this->counts($workspace));
        $fresh = $workspace->fresh();
        $this->assertSame($workspace->slug, $fresh->slug);
        $this->assertSame($workspace->code, $fresh->code);
        $this->assertSame($owner->id, $fresh->owner_id);
        $this->assertSame($seatCount, Seat::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(1, PosProduct::query()->where('workspace_id', $workspace->id)->count());
        $this->assertTrue(User::query()->whereKey($owner->id)->exists());
        Storage::disk('public')->assertMissing("receipts/{$workspace->id}.jpg");
    }

    public function test_reset_writes_an_audit_entry_with_actor_and_reason(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->reset($owner, $workspace, ['reason' => 'Season over'])->assertOk();

        $log = AuditLog::query()->where('action', 'workspace.reset')->firstOrFail();
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame('Season over', $log->reason);
        $this->assertSame($workspace->id, $log->workspace_id);
    }

    public function test_other_workspaces_are_not_affected(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();
        $this->seedOperationalData($workspace);
        $this->seedOperationalData($other);
        $before = $this->counts($other);

        $this->reset($owner, $workspace)->assertOk();

        $this->assertSame($before, $this->counts($other));
        $this->assertSame(1, $before['invoices']);
    }

    public function test_a_step_registered_by_another_module_runs_automatically(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $fake = new class implements WorkspaceResetStep
        {
            public ?string $ranFor = null;

            public function key(): string
            {
                return 'fake-module';
            }

            public function reset(Workspace $workspace): void
            {
                $this->ranFor = (string) $workspace->id;
            }

            public function filePaths(Workspace $workspace): array
            {
                return [];
            }
        };
        $this->app->tag([$fake::class], 'workspace.reset');
        $this->app->instance($fake::class, $fake);
        $this->app->forgetInstance(WorkspaceResetter::class);

        $this->reset($owner, $workspace)->assertOk();

        $this->assertSame($workspace->id, $fake->ranFor);
    }

    public function test_a_failing_step_rolls_everything_back(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $this->seedOperationalData($workspace);
        $before = $this->counts($workspace);
        app(WorkspaceResetter::class)->register(new class implements WorkspaceResetStep
        {
            public function key(): string
            {
                return 'boom';
            }

            public function reset(Workspace $workspace): void
            {
                throw new RuntimeException('step failed');
            }

            public function filePaths(Workspace $workspace): array
            {
                return [];
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->reset($owner, $workspace);
            $this->fail('The failing step should have aborted the reset.');
        } catch (RuntimeException) {
            $this->assertSame($before, $this->counts($workspace));
            $this->assertSame(0, AuditLog::query()->where('action', 'workspace.reset')->count());
        }
    }

    public function test_wrong_confirmation_is_rejected_and_deletes_nothing(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $this->seedOperationalData($workspace);
        $before = $this->counts($workspace);

        $this->reset($owner, $workspace, ['confirmation' => 'not-the-slug'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmation');

        $this->assertSame($before, $this->counts($workspace));
    }

    public function test_reason_is_required(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->reset($owner, $workspace, ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_enrolled_owner_without_recent_two_factor_is_forbidden(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace(verified: false);
        $this->seedOperationalData($workspace);
        $before = $this->counts($workspace);

        $this->reset($owner, $workspace)->assertForbidden()->assertJsonPath('code', 'two_factor_required');

        $this->assertSame($before, $this->counts($workspace));
    }

    public function test_enrolled_owner_with_recent_two_factor_can_reset(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->reset($owner, $workspace)->assertOk();
    }

    public function test_owner_without_two_factor_enabled_cannot_reset(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace(twoFactor: false);
        $this->seedOperationalData($workspace);
        $before = $this->counts($workspace);

        $this->reset($owner, $workspace)
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_enrollment_required');

        $this->assertSame($before, $this->counts($workspace));
    }

    public function test_non_owners_are_forbidden(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $freelancer = User::factory()->freelancer()->create();
        $this->seedOperationalData($workspace);
        $before = $this->counts($workspace);

        $this->reset($freelancer, $workspace)->assertForbidden();

        $this->assertSame($before, $this->counts($workspace));
    }
}
