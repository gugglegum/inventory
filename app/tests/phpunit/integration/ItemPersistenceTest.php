<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты базового сохранения предметов и связанных данных.
 *
 * Защищают repo-scoped itemId, замену тегов и поведение soft delete в Item::find().
 */
final class ItemPersistenceTest extends DbTestCase
{
    /**
     * Создание предметов назначает последовательные itemId внутри репозитория и обновляет счетчик repo.lastItemId.
     */
    public function testCreatingItemsAssignsRepoScopedItemIdsAndUpdatesRepoCounter(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS
        );

        $container = $this->createItem($repo, $user, [
            'name' => 'Большая коробка',
            'isContainer' => true,
        ]);
        $child = $this->createItem($repo, $user, [
            'name' => 'Переходник DVI',
            'parentItemId' => $container->itemId,
        ]);

        self::assertSame(1, (int) $container->itemId);
        self::assertSame(2, (int) $child->itemId);
        self::assertSame((int) $container->itemId, (int) $child->parentItemId);

        $repo->refresh();
        self::assertSame(2, (int) $repo->lastItemId);
    }

    /**
     * Повторное сохранение тегов из строки заменяет старый набор тегов новым.
     */
    public function testSavingTagsFromStringReplacesExistingTags(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'USB кабель',
        ]);

        $item->saveTagsFromString('кабель, usb, черный');
        self::assertEqualsCanonicalizing(['кабель', 'usb', 'черный'], $item->fetchTags());

        $item->saveTagsFromString('usb, белый');
        self::assertEqualsCanonicalizing(['usb', 'белый'], $item->fetchTags());

        self::assertSame(2, (int) $item->getItemTags()->count());
    }

    /**
     * Стандартный Item::find() не возвращает мягко удаленные предметы, но findWithDeleted() их видит.
     */
    public function testDefaultItemQueryHidesSoftDeletedItems(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS
        );
        $item = $this->createItem($repo, $user, [
            'name' => 'Старая коробка',
            'isContainer' => true,
        ]);

        self::assertTrue($item->softDelete($user->id));

        self::assertNull(Item::findOne($item->id));
        self::assertNotNull(Item::findWithDeleted()->where(['id' => $item->id])->one());
    }
}
