<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The sample login testuser and a few staff members with filled profiles and sample roles.
 *
 * On an empty database testuser is the first user, so it becomes the owner. Every
 * account's password is "password". Running this again keeps one testuser and adds
 * more staff.
 *
 * Extending:
 * - Staff get a role from RoleSeeder::ROLES; run RoleSeeder first.
 */
class UserSeeder extends Seeder
{
    /** Staff members added on each run. */
    public const COUNT = 6;

    /**
     * Creates testuser when missing and the sample staff.
     */
    public function run(): void
    {
        if (! User::query()->where('username', 'testuser')->exists()) {
            User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
                'firstname' => 'Test',
                'lastname' => 'User',
            ]);
        }

        $roles = array_keys(RoleSeeder::ROLES);

        User::factory(self::COUNT)->staff()->create()->each(function (User $user) use ($roles): void {
            $user->assignRole(fake()->randomElement($roles));
        });
    }
}
