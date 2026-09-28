<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds fake staff users for tests and local sample data.
 *
 * Every user gets a unique username, email, and Iranian mobile number, a verified
 * email, and the password "password". The first user created on an empty database
 * becomes the owner, so tests create the owner before any other staff member.
 *
 * Extending:
 * - A new users column needs a default here so User::factory()->create() keeps working.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('09#########'),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'last_login' => null,
            'last_login_ip' => null,
            'code' => null,
            'token' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
