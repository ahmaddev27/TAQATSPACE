<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'title' => fake()->randomElement([
                'عرض الشهر',
                'تحديث مواعيد العمل',
                'صيانة مجدولة',
                'إنترنت أسرع الآن',
            ]),
            'body' => 'نودّ إعلامكم بآخر المستجدات في المساحة. شكراً لكونكم جزءاً من مجتمعنا.',
            'type' => fake()->randomElement(AnnouncementType::cases()),
            'published_at' => now()->subDays(fake()->numberBetween(0, 20)),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 week', '+2 months'),
            'created_by' => User::factory()->owner(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => null,
        ]);
    }
}
