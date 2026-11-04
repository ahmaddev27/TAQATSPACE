<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\SeatKind;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\Specialization;
use App\Models\Seat;
use App\Services\BranchService;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;

/**
 * The first tenant's stations (zones, a second branch, a private office) and the
 * donor fields of its members: one complete profile, the rest incomplete.
 * The workspace holds 12 seats and the membership step already created 4, so
 * this adds 4 and leaves room for the checks that add their own.
 */
class StationsStep implements DemoSeedStep
{
    public const SECOND_BRANCH = 'فرع الشاطئ';

    /** @var list<array{0: string, 1: SeatKind, 2: SeatType, 3: string, 4: int, 5: bool, 6: bool}> number, kind, type, zone, capacity, private, second branch */
    private const STATIONS = [
        ['Z1-1', SeatKind::Desk, SeatType::Flexible, 'الطابق الأول', 1, false, false],
        ['Z1-2', SeatKind::Desk, SeatType::Flexible, 'الطابق الأول', 1, false, false],
        ['MEET-1', SeatKind::Room, SeatType::Flexible, 'الطابق الثاني', 8, false, true],
        ['OFFICE-1', SeatKind::Room, SeatType::PrivateOffice, 'الطابق الثاني', 4, true, true],
    ];

    public function __construct(private readonly BranchService $branches) {}

    public function seed(DemoContext $context): void
    {
        $workspace = $context->workspace('ws1');
        $branch = $this->branches->create($workspace, ['name' => self::SECOND_BRANCH, 'address' => 'شارع الرشيد، غزة']);
        $defaultBranchId = $this->branches->defaultBranchId($workspace);

        foreach (self::STATIONS as [$number, $kind, $type, $zone, $capacity, $private, $inSecondBranch]) {
            Seat::query()->create([
                'workspace_id' => $workspace->id,
                'branch_id' => $inSecondBranch ? $branch->id : $defaultBranchId,
                'seat_number' => $number,
                'type' => $type,
                'kind' => $kind,
                'zone' => $zone,
                'capacity' => $capacity,
                'is_private' => $private,
                'status' => SeatStatus::Available,
            ]);
        }

        $this->completeProfile($context);
    }

    /** The first subscriber is complete; every other member of the tenant has nothing filled in. */
    private function completeProfile(DemoContext $context): void
    {
        $context->user('freelancer1')->forceFill([
            'gender' => Gender::Female,
            'education_level' => EducationLevel::Bachelor,
            'specialization' => Specialization::SoftwareEngineering,
            'employment_status' => EmploymentStatus::Freelancer,
        ])->save();
    }
}
