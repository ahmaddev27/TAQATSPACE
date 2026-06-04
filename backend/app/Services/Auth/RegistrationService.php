<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\SeatType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewWorkspaceRegistrationNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\FileUploadService;
use App\Services\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly FileUploadService $files,
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * Register a freelancer or workspace owner.
     *
     * @param  array<string, mixed>  $data  validated, excluding files and password_confirmation
     * @return array{user: User, token: string}
     */
    public function register(array $data, ?UploadedFile $license = null, ?UploadedFile $idDocument = null): array
    {
        $role = UserRole::from($data['role']);

        $user = DB::transaction(function () use ($data, $role, $license, $idDocument): User {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'role' => $role->value,
                'status' => UserStatus::PendingVerification->value,
                'specialty' => $data['specialty'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]);

            $user->assignRole($role->value);

            if ($role === UserRole::WorkspaceOwner) {
                if ($license !== null && $idDocument !== null) {
                    $user->update([
                        'documents' => [
                            'license_file' => $this->files->upload($license, "owner-docs/{$user->id}"),
                            'id_document' => $this->files->upload($idDocument, "owner-docs/{$user->id}"),
                        ],
                    ]);
                }

                $this->workspaces->createForOwner(
                    (string) $user->id,
                    $this->workspaceAttributes($data),
                );
            }

            return $user;
        });

        if ($role === UserRole::WorkspaceOwner) {
            $this->notifyAdmins($user);
        }

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }

    /**
     * Map the workspace-owner registration payload onto the columns that
     * {@see WorkspaceService::createForOwner()} expects. `name` here is the
     * SPACE name (submitted as `workspace_name`); the owner's personal name
     * stays on the user record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function workspaceAttributes(array $data): array
    {
        /** @var array<int, array<string, mixed>> $seatTypes */
        $seatTypes = $data['seat_types'] ?? [];

        return [
            'name' => $data['workspace_name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'phone' => $data['phone'] ?? null,
            'latitude' => $data['lat'] ?? null,
            'longitude' => $data['lng'] ?? null,
            'total_seats' => (int) ($data['capacity'] ?? 0),
            'price_per_month' => $this->primaryMonthlyPrice($seatTypes),
            'amenities' => $data['amenities'] ?? [],
            'working_hours' => $this->workingHours($data['hours'] ?? null),
            'seat_types' => $seatTypes,
        ];
    }

    /**
     * Keep the legacy single-price column sensible: prefer the Fixed seat type's
     * monthly price, otherwise the first enabled type that has one.
     *
     * @param  array<int, array<string, mixed>>  $seatTypes
     */
    private function primaryMonthlyPrice(array $seatTypes): float
    {
        $enabled = array_filter(
            $seatTypes,
            static fn (array $row): bool => (bool) ($row['enabled'] ?? false)
                && ($row['price_monthly'] ?? null) !== null
                && $row['price_monthly'] !== '',
        );

        foreach ($enabled as $row) {
            if (($row['type'] ?? null) === SeatType::Fixed->value) {
                return (float) $row['price_monthly'];
            }
        }

        $first = reset($enabled);

        return $first === false ? 0.0 : (float) $first['price_monthly'];
    }

    /**
     * The form submits a free-text range (e.g. "8am – 10pm"); store it under a
     * `general` key so the JSON column shape stays forward-compatible with the
     * eventual per-day structure.
     *
     * @return array<string, string>|null
     */
    private function workingHours(?string $hours): ?array
    {
        $hours = is_string($hours) ? trim($hours) : '';

        return $hours === '' ? null : ['general' => $hours];
    }

    private function notifyAdmins(User $owner): void
    {
        User::query()
            ->where('role', UserRole::Admin->value)
            ->each(fn (User $admin) => $admin->notify(new NewWorkspaceRegistrationNotification($owner)));
    }
}
