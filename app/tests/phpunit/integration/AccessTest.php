<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\components\ItemAccessValidator;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты проверки доступа к репозиториям.
 *
 * Фиксируют контракт ItemAccessValidator вокруг записей repo_user и битовой маски прав.
 */
final class AccessTest extends DbTestCase
{
    /**
     * Доступ к репозиторию требует явной строки repo_user для текущего пользователя.
     */
    public function testRepoAccessRequiresRepoUserRow(): void
    {
        $owner = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($owner);
        $otherUser = $this->createUser();
        $this->login($otherUser);

        $validator = new ItemAccessValidator();

        self::assertFalse($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_READONLY));

        $this->grantRepoAccess($repo, $otherUser, RepoUser::ACCESS_READONLY);

        self::assertTrue($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_READONLY));
        self::assertFalse($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_CREATE_ITEMS));
    }

    /**
     * Валидатор отличает разрешенные и запрещенные права внутри битовой маски доступа.
     */
    public function testRepoAccessChecksBitmaskPermissions(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $validator = new ItemAccessValidator();

        self::assertTrue($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_CREATE_ITEMS));
        self::assertTrue($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_EDIT_ITEMS));
        self::assertFalse($validator->hasUserAccessToRepo($repo, RepoUser::ACCESS_DELETE_ITEMS));
    }
}
