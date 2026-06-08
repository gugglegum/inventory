<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use common\models\User;
use tests\phpunit\TestCase;

/**
 * Unit-тесты базовой модели пользователя.
 *
 * Проверяют низкоуровневые методы пароля и authKey без обращения к базе.
 */
final class UserTest extends TestCase
{
    /**
     * Хеш пароля принимает исходный пароль и отклоняет неверный.
     */
    public function testPasswordHashCanBeValidated(): void
    {
        $user = new User();
        $user->setPassword('secret-password');

        self::assertTrue($user->validatePassword('secret-password'));
        self::assertFalse($user->validatePassword('wrong-password'));
    }

    /**
     * Сгенерированный authKey проходит проверку, а произвольный ключ отклоняется.
     */
    public function testAuthKeyCanBeGeneratedAndValidated(): void
    {
        $user = new User();
        $user->generateAuthKey();

        self::assertNotEmpty($user->authKey);
        self::assertTrue($user->validateAuthKey($user->authKey));
        self::assertFalse($user->validateAuthKey('invalid-auth-key'));
    }
}
