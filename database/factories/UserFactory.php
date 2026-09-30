<?php

namespace Database\Factories;

use App\Models\Degree;
use App\Models\Gender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds fake staff users for tests and local sample data.
 *
 * Every user gets a Persian name, a unique username, email, and Iranian mobile number, a verified
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
            'firstname' => fake('fa_IR')->firstName(),
            'lastname' => fake('fa_IR')->lastName(),
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
     * A staff member with a filled profile: personnel number, national code, job, degree, major, and gender.
     *
     * The first name matches the gender.
     */
    public function staff(): static
    {
        return $this->state(function (): array {
            $persian = fake('fa_IR');
            $gender = $persian->randomElement([Gender::WOMAN, Gender::MAN]);

            return [
                'firstname' => $gender === Gender::MAN ? $persian->firstNameMale() : $persian->firstNameFemale(),
                'personnel' => fake()->unique()->numerify('1####'),
                'national' => fake()->unique()->numerify('##########'),
                'job' => $persian->randomElement(['نویسنده', 'ویراستار', 'کارشناس پشتیبانی', 'کارشناس بازاریابی', 'طراح']),
                'degree' => $persian->randomElement([Degree::DIPLOMA, Degree::ASSOCIATE, Degree::BACHELOR, Degree::MASTER]),
                'major' => $persian->randomElement(['مهندسی کامپیوتر', 'مدیریت', 'ادبیات فارسی', 'گرافیک', 'روزنامه‌نگاری']),
                'gender' => $gender,
            ];
        });
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
