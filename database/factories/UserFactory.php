<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * One password across the whole suite, hashed once at four rounds in the testing
     * environment. A test that needs to sign in reads it from here rather than inventing
     * its own, so "which password does this user have" is never a question.
     */
    public const PASSWORD = 'correct-horse-battery';

    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::PASSWORD,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ];
    }
}
