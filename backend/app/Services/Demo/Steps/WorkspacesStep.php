<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\StaffGroup;
use App\Enums\UserRole;
use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\Workspace;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoMarker;
use App\Services\Demo\DemoSeedStep;
use Database\Seeders\GazaData;

/** The two isolated tenants, the throwaway one for the reset test, and the first tenant's staff. */
class WorkspacesStep implements DemoSeedStep
{
    use BuildsDemoUsers;

    private const WORKSPACES = [
        'ws1' => ['owner' => 'owner1', 'name' => 'مساحة المنارة', 'slug' => 'almanara', 'area' => 'الرمال', 'price' => 40],
        'ws2' => ['owner' => 'owner2', 'name' => 'مساحة إبداع', 'slug' => 'ibdaa', 'area' => 'تل السلطان', 'price' => 35],
        'ws_throwaway' => ['owner' => 'owner_throwaway', 'name' => 'مساحة تجريبية للحذف', 'slug' => 'throwaway', 'area' => 'المدينة', 'price' => 25],
    ];

    private const STAFF = [
        'staff_cashier' => ['name' => 'دعاء الكحلوت', 'group' => StaffGroup::Cashier],
        'staff_hospitality' => ['name' => 'محمود زقوت', 'group' => StaffGroup::Hospitality],
        'staff_finance' => ['name' => 'رنا صيدم', 'group' => StaffGroup::FinanceSupervisor],
    ];

    public function seed(DemoContext $context): void
    {
        foreach (self::WORKSPACES as $key => $definition) {
            $context->addWorkspace($key, $this->makeWorkspace($context, $definition));
        }

        $this->seedStaff($context);
    }

    /**
     * @param  array{owner: string, name: string, slug: string, area: string, price: int}  $definition
     */
    private function makeWorkspace(DemoContext $context, array $definition): Workspace
    {
        return Workspace::query()->create([
            'owner_id' => $context->user($definition['owner'])->id,
            'name' => $definition['name'],
            'slug' => DemoMarker::slugPrefix().$definition['slug'],
            'description' => 'مساحة عمل تجريبية لاختبار المنصة على بيئة الاختبار.',
            'address' => $definition['area'].'، غزة',
            'city' => 'غزة',
            // Required by the settings form, so a workspace without it can never be saved.
            'city_id' => City::query()->firstOrCreate(['name_ar' => 'غزة'], ['name_en' => 'Gaza'])->id,
            'total_seats' => 12,
            'price_per_month' => $definition['price'],
            'amenities' => ['wifi', 'printer', 'coffee'],
            'working_hours' => $this->workingHours(),
            'status' => WorkspaceStatus::Active,
            'published_at' => now(),
        ]);
    }

    /** @return array<string, array{open: string, close: string}|string> */
    private function workingHours(): array
    {
        return GazaData::workingHours();
    }

    private function seedStaff(DemoContext $context): void
    {
        $workspace = $context->workspace('ws1');

        foreach (self::STAFF as $key => $staff) {
            $user = $this->makeUser($context, $key, $staff['name'], UserRole::Cashier, [
                'workspace_id' => $workspace->id,
                'staff_group' => $staff['group'],
            ]);

            $user->assignRole(UserRole::Cashier->value);
            $user->syncPermissions($staff['group']->permissions());
        }
    }
}
