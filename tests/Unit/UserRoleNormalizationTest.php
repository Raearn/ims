<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRoleNormalizationTest extends TestCase
{
    public function test_role_accessor_trims_and_lowercases(): void
    {
        $user = new User(['role' => ' Supervisor ']);

        $this->assertSame('supervisor', $user->role);
    }

    public function test_role_accessor_preserves_null(): void
    {
        $user = new User;

        $this->assertNull($user->role);
    }
}
