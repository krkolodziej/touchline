<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'type' => NotificationType::MatchFinished,
            'title' => 'Stal 2–1 Resovia',
            'body' => 'Round 1 has finished.',
            'link' => '/dashboard',
            'dedupe_key' => Str::random(24),
            'read_at' => null,
        ];
    }
}
