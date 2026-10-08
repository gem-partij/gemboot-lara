<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Auth\GembootUser;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Tests\TestCase;

class GembootUserTest extends TestCase
{
    function test_the_id_comes_from_id_or_a_wrapped_user()
    {
        $this->assertSame(7, (new GembootUser(['id' => 7]))->getAuthIdentifier());
        $this->assertSame(8, (new GembootUser(['user' => ['id' => 8], 'roles' => []]))->getAuthIdentifier());
    }

    function test_attributes_read_like_properties()
    {
        $user = new GembootUser(['id' => 7, 'name' => 'Ana']);

        $this->assertSame('Ana', $user->name);
        $this->assertTrue(isset($user->name));
        $this->assertNull($user->missing);
        $this->assertSame(['id' => 7, 'name' => 'Ana'], $user->toArray());
        $this->assertSame('{"id":7,"name":"Ana"}', json_encode($user));
    }

    function test_known_roles_and_permissions_answer_locally()
    {
        $user = new GembootUser(['id' => 7], roles: ['editor'], permissions: ['a', 'b']);

        $this->assertTrue($user->hasRole('admin|editor'));
        $this->assertTrue($user->hasRole(['admin', 'editor']));
        $this->assertFalse($user->hasRole('admin'));

        $this->assertTrue($user->hasPermissionTo('a|b'));
        $this->assertFalse($user->hasPermissionTo('a|c'));
        $this->assertTrue($user->hasPermissionTo(['a', 'c']));
        $this->assertFalse($user->hasPermissionTo(['c']));
    }

    function test_unknown_roles_and_permissions_ask_the_auth_service()
    {
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        $fake = GembootAuth::fake(roles: ['admin'], permissions: ['a']);
        request()->headers->set('Authorization', 'Bearer t1');

        $user = GembootUser::fromAuthService(['id' => 7]);

        $this->assertNull($user->roles());
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasPermissionTo('a'));
        $fake->assertCalled('has-role', 1)->assertCalled('has-permission-to', 1);
    }
}
