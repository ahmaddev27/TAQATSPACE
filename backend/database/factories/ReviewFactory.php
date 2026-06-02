<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => User::factory()->freelancer(),
            'workspace_id' => Workspace::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->randomElement([
                'مكان ممتاز وهادئ ومناسب للعمل.',
                'الإنترنت سريع والخدمة رائعة.',
                'موقع جيد لكن المقاعد محدودة أحياناً.',
                'تجربة عمل مريحة وطاقم متعاون.',
                'أنصح به بشدة للمستقلين.',
            ]),
        ];
    }
}
