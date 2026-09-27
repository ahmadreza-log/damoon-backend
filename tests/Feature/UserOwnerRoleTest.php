<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOwnerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_first_registered_user_receives_the_owner_role(): void
    {
        $first = User::factory()->create([
            'roles' => ['editor'],
        ]);
        $second = User::factory()->create([
            'roles' => ['owner', 'editor'],
        ]);

        $this->assertSame(['editor', 'owner'], $first->fresh()->roles);
        $this->assertTrue($first->fresh()->owner());
        $this->assertSame(['editor'], $second->fresh()->roles);
        $this->assertFalse($second->fresh()->owner());
    }

    public function test_owner_role_cannot_be_moved_to_another_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $member->roles = ['owner'];
        $member->save();

        $owner->roles = ['editor'];
        $owner->save();

        $this->assertTrue($owner->fresh()->owner());
        $this->assertSame(['editor', 'owner'], $owner->fresh()->roles);
        $this->assertFalse($member->fresh()->owner());
        $this->assertSame([], $member->fresh()->roles);
    }
}
