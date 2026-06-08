<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\models\UserForm;
use backend\services\UserFormService;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты сервиса подготовки и сохранения формы пользователя.
 *
 * Проверяют create/update сценарии UserForm без HTTP-обвязки UsersController.
 */
final class UserFormServiceTest extends DbTestCase
{
    /**
     * prepareForCreate() выставляет create-сценарий, а save() создает пользователя с паролем.
     */
    public function testPrepareForCreateAndSaveCreatesUserWithPassword(): void
    {
        $service = new UserFormService();
        $form = $service->prepareForCreate();

        $result = $service->save($form, [
            'UserForm' => [
                'username' => 'created_service_user',
                'email' => 'created-service-user@example.test',
                'password' => 'secret123',
                'status' => User::STATUS_ACTIVE,
            ],
        ]);

        $user = $form->getUser();

        self::assertTrue($result);
        self::assertSame(UserForm::SCENARIO_CREATE, $form->scenario);
        self::assertFalse($user->isNewRecord);
        self::assertSame('created_service_user', $user->username);
        self::assertSame('created-service-user@example.test', $user->email);
        self::assertSame(User::STATUS_ACTIVE, (int) $user->status);
        self::assertTrue($user->validatePassword('secret123'));
        self::assertNotEmpty($user->authKey);
    }

    /**
     * prepareForUpdate() заполняет форму текущими данными, а save() обновляет пользователя без смены пароля.
     */
    public function testPrepareForUpdateAndSaveUpdatesUserWithoutChangingPassword(): void
    {
        $user = $this->createUser([
            'username' => 'old_service_user',
            'email' => 'old-service-user@example.test',
            'password' => 'oldSecret123',
        ]);
        $oldPasswordHash = $user->passwordHash;
        $oldAuthKey = $user->authKey;
        $service = new UserFormService();

        $form = $service->prepareForUpdate($user);
        self::assertSame('old_service_user', $form->username);
        self::assertSame('old-service-user@example.test', $form->email);

        $result = $service->save($form, [
            'UserForm' => [
                'username' => 'updated_service_user',
                'email' => 'updated-service-user@example.test',
                'password' => '',
                'status' => User::STATUS_DELETED,
            ],
        ]);

        $user->refresh();

        self::assertTrue($result);
        self::assertSame('updated_service_user', $user->username);
        self::assertSame('updated-service-user@example.test', $user->email);
        self::assertSame(User::STATUS_DELETED, (int) $user->status);
        self::assertSame($oldPasswordHash, $user->passwordHash);
        self::assertSame($oldAuthKey, $user->authKey);
        self::assertTrue($user->validatePassword('oldSecret123'));
    }
}
