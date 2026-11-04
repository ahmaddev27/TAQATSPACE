<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\PosPermissionSeeder;
use Spatie\Permission\Models\Role;

/** Platform admins, workspace owners and the ten freelancers. */
class AccountsStep implements DemoSeedStep
{
    use BuildsDemoUsers;

    private const FREELANCERS = [
        'سامي الفرا', 'نور حمدان', 'يوسف أبو رمضان', 'ليان الأغا', 'خالد شاهين',
        'هبة النجار', 'عمر مقداد', 'مريم الشوا', 'باسل أبو شعبان', 'آية الريّس',
    ];

    public function seed(DemoContext $context): void
    {
        $this->ensureRolesAndPermissions();
        $this->seedAdmins($context);
        $this->seedOwners($context);
        $this->seedFreelancers($context);
    }

    private function ensureRolesAndPermissions(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        (new AdminPermissionSeeder)->run();
        (new PosPermissionSeeder)->run();
    }

    private function seedAdmins(DemoContext $context): void
    {
        $this->makeUser($context, 'admin', 'مشرف العرض التجريبي', UserRole::Admin)
            ->assignRole(AdminRole::SuperAdmin->value);

        $limited = $this->makeUser($context, 'admin_limited', 'مشرف محدود الصلاحيات', UserRole::Admin);
        $limited->assignRole(AdminRole::Admin->value);
        $limited->syncPermissions(array_values(array_diff(
            AdminPermission::defaultsForAdmin(),
            [AdminPermission::ApproveRequests->value],
        )));
    }

    private function seedOwners(DemoContext $context): void
    {
        $documents = ['license' => 'owner-docs/demo/license.pdf', 'id_document' => 'owner-docs/demo/id.pdf'];

        foreach (['owner1' => 'أحمد المنارة', 'owner2' => 'سلمى الإبداع', 'owner_throwaway' => 'رامي التجريبي'] as $key => $name) {
            $this->makeUser($context, $key, $name, UserRole::WorkspaceOwner, ['documents' => $documents]);
        }
    }

    private function seedFreelancers(DemoContext $context): void
    {
        foreach (self::FREELANCERS as $index => $name) {
            $this->makeUser($context, 'freelancer'.($index + 1), $name, UserRole::Freelancer, [
                'specialty' => 'مستقل',
            ]);
        }
    }
}
