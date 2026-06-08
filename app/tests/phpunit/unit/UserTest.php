<?php

namespace tests\phpunit\unit;

use common\models\User;
use tests\phpunit\TestCase;

final class UserTest extends TestCase
{
    public function testPasswordHashCanBeValidated(): void
    {
        $user = new User();
        $user->setPassword('secret-password');

        self::assertTrue($user->validatePassword('secret-password'));
        self::assertFalse($user->validatePassword('wrong-password'));
    }

    public function testAuthKeyCanBeGeneratedAndValidated(): void
    {
        $user = new User();
        $user->generateAuthKey();

        self::assertNotEmpty($user->authKey);
        self::assertTrue($user->validateAuthKey($user->authKey));
        self::assertFalse($user->validateAuthKey('invalid-auth-key'));
    }
}
