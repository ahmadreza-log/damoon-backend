<?php

namespace Tests\Feature;

use App\Auth\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the owner role: there is always exactly one owner.
 *
 * The first user ever created becomes the owner automatically and gets every section.
 * The role can neither be given to a second user nor taken from the owner.
 *
 * Extending:
 * - The guards are User::hold() and User::keep(), fired by Spatie's role events.
 */
class UserOwnerRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The first user is the owner with every section; the second user has no role and no sections.
     */
    public function test_only_the_first_registered_user_receives_the_owner_role(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertTrue($first->fresh()->owner());
        $this->assertEqualsCanonicalizing(Section::keys(), $first->fresh()->sections());
        $this->assertFalse($second->fresh()->owner());
        $this->assertSame([], $second->fresh()->sections());
    }

    /**
     * Assigning the owner role to another user and removing it from the owner are both undone.
     */
    public function test_owner_role_cannot_be_moved_to_another_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $member->assignRole(User::ROLE_OWNER);
        $owner->removeRole(User::ROLE_OWNER);

        $this->assertTrue($owner->fresh()->owner());
        $this->assertFalse($member->fresh()->owner());
    }
}
