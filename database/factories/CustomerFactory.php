<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected static ?string $password;

    /**
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
        ];
    }
}
