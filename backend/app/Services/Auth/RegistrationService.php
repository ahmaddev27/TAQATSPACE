<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewWorkspaceRegistrationNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly FileUploadService $files,
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

            if ($role === UserRole::WorkspaceOwner && $license !== null && $idDocument !== null) {
                $user->update([
                    'documents' => [
                        'license_file' => $this->files->upload($license, "owner-docs/{$user->id}"),
                        'id_document' => $this->files->upload($idDocument, "owner-docs/{$user->id}"),
                    ],
                ]);
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

    private function notifyAdmins(User $owner): void
    {
        User::query()
            ->where('role', UserRole::Admin->value)
            ->each(fn (User $admin) => $admin->notify(new NewWorkspaceRegistrationNotification($owner)));
    }
}
