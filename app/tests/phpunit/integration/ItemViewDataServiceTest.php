<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemViewDataService;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты read-side сервиса просмотра предмета.
 *
 * Проверяют дочерние предметы, последние заметки, навигацию prev/next и path-данные для JSON-preview.
 */
final class ItemViewDataServiceTest extends DbTestCase
{
    /**
     * prepare() возвращает детей контейнера и соседние предметы по itemId.
     */
    public function testPrepareReturnsChildrenAndNavigationItems(): void
    {
        [$repo, $container, $firstChild, $secondChild, $thirdChild] = $this->prepareFixture();

        $viewData = (new ItemViewDataService())->prepare($secondChild);

        self::assertSame([], $viewData->children);
        self::assertNotNull($viewData->prevItem);
        self::assertNotNull($viewData->nextItem);
        self::assertSame((int) $firstChild->id, (int) $viewData->prevItem->id);
        self::assertSame((int) $thirdChild->id, (int) $viewData->nextItem->id);

        $containerViewData = (new ItemViewDataService())->prepare($container);
        self::assertSame(
            [(int) $secondChild->id, (int) $firstChild->id, (int) $thirdChild->id],
            array_map(static fn(Item $item): int => (int) $item->id, $containerViewData->children)
        );
    }

    /**
     * prepare() возвращает общее число заметок и только пять самых новых.
     */
    public function testPrepareReturnsRecentPostsAndTotalCount(): void
    {
        [, , , $item] = $this->prepareFixture();
        $user = User::findOne((int) $item->createdBy);
        self::assertNotNull($user);

        $posts = [];
        for ($index = 1; $index <= 7; $index++) {
            $posts[$index] = $this->createPost($item, $user, [
                'datetime' => 1_700_000_000 + $index,
                'title' => 'Заметка ' . $index,
            ]);
        }

        $viewData = (new ItemViewDataService())->prepare($item);

        self::assertSame(7, $viewData->postCount);
        self::assertSame(
            [$posts[7]->id, $posts[6]->id, $posts[5]->id, $posts[4]->id, $posts[3]->id],
            array_column($viewData->recentPosts, 'id')
        );
    }

    /**
     * preparePreview() строит путь от предмета к его родительскому контейнеру.
     */
    public function testPreparePreviewBuildsItemPath(): void
    {
        [$repo, $container, $firstChild, $secondChild] = $this->prepareFixture();

        $previewData = (new ItemViewDataService())->preparePreview($secondChild);
        $path = $previewData->paths[$secondChild->id];

        self::assertSame(
            [(int) $secondChild->itemId, (int) $container->itemId],
            array_map(static fn(array $pathItem): int => (int) $pathItem['itemId'], $path)
        );
        self::assertSame('Второй предмет', $path[0]['label']);
        self::assertSame('Контейнер', $path[1]['label']);
        self::assertSame(['items/view', 'repoId' => $repo->id, 'itemId' => (int) $secondChild->itemId], $path[0]['url']);
        self::assertSame(['items/view', 'repoId' => $repo->id, 'itemId' => (int) $container->itemId], $path[1]['url']);
    }

    /**
     * Создает контейнер с тремя дочерними предметами.
     *
     * @return array{0:\common\models\Repo, 1:\common\models\Item, 2:\common\models\Item, 3:\common\models\Item, 4:\common\models\Item}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $container = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $firstChild = $this->createItem($repo, $user, [
            'name' => 'Первый предмет',
            'parentItemId' => $container->itemId,
            'priority' => 10,
        ]);
        $secondChild = $this->createItem($repo, $user, [
            'name' => 'Второй предмет',
            'parentItemId' => $container->itemId,
            'isContainer' => true,
            'priority' => 10,
        ]);
        $thirdChild = $this->createItem($repo, $user, [
            'name' => 'Третий предмет',
            'parentItemId' => $container->itemId,
            'priority' => 1,
        ]);

        return [$repo, $container, $firstChild, $secondChild, $thirdChild];
    }
}
