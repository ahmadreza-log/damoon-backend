<?php

namespace Tests\Feature;

use App\Auth\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOwnerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_first_registered_user_receives_the_owner_role(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertTrue($first->fresh()->owner());
        $this->assertEqualsCanonicalizing(Section::keys(), $first->fresh()->sections());
        $this->assertFalse($second->fresh()->owner());
        $this->assertSame([], $second->fresh()->sections());
    }

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
