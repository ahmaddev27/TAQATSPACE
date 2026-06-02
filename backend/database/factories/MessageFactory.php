<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory()->owner(),
            'receiver_id' => User::factory()->freelancer(),
            'workspace_id' => Workspace::factory(),
            'type' => MessageType::Direct,
            'content' => fake()->randomElement([
                'أهلاً بك في المساحة، إن احتجت أي شيء فلا تتردد.',
                'تم تجهيز مقعدك، يمكنك الحضور في أي وقت.',
                'تذكير: موعد تجديد الاشتراك قريب.',
                'يوجد لدينا قاعة اجتماعات متاحة هذا الأسبوع.',
            ]),
            'read_at' => fake()->optional(0.6)->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function broadcast(): static
    {
        return $this->state(fn (array $attributes): array => [
            'receiver_id' => null,
            'type' => MessageType::Broadcast,
            'read_at' => null,
            'content' => fake()->randomElement([
                'سيتم إغلاق المساحة يوم الجمعة بمناسبة العطلة.',
                'أصبح الإنترنت الجديد عالي السرعة متاحاً للجميع.',
                'عرض خاص: خصم 10% على التجديد الشهري.',
            ]),
        ]);
    }
}
