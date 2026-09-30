<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Builds fake customers for tests and local sample data.
 *
 * Every customer gets a Persian name, a unique username, email, and Iranian mobile number, a
 * verified email, and the password "password".
 *
 * Extending:
 * - A new customers column needs a default here so Customer::factory()->create() keeps working.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The hash of "password", computed once and reused, because hashing is slow on purpose.
     */
    protected static ?string $password;

    /**
     * The default column values for one fake customer; tests override any of them with create([...]).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('09#########'),
            'firstname' => fake('fa_IR')->firstName(),
            'lastname' => fake('fa_IR')->lastName(),
            'last_login' => null,
            'last_login_ip' => null,
            'code' => null,
            'token' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }
}
