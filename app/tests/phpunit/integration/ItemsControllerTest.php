<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\ItemsController;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты HTTP-сценариев ItemsController.
 *
 * Сохраняют regression-покрытие для поиска и импорта после выноса бизнес-логики в сервисы.
 */
final class ItemsControllerTest extends DbTestCase
{
    /**
     * GET index рендерит корневой список предметов репозитория.
     */
    public function testIndexRendersRootItemList(): void
    {
        [$controller, $repo, $rootContainer, $rootItem] = $this->prepareItemListFixture();

        $this->setGetRequest();

        $response = $controller->actionIndex($repo->id);

        self::assertIsString($response);
        self::assertStringContainsString($repo->name, $response);
        self::assertStringContainsString($rootContainer->name, $response);
        self::assertStringContainsString($rootItem->name, $response);
    }

    /**
     * GET pick-container рендерит выбранный контейнер и его дочерние контейнеры.
     */
    public function testPickContainerRendersSelectedContainerLevel(): void
    {
        [$controller, $repo, $rootContainer, , $childContainer] = $this->prepareItemListFixture();

        $this->setGetRequest();

        $response = $controller->actionPickContainer($repo->id, (string) $rootContainer->itemId);

        self::assertIsString($response);
        self::assertSame('blank', $controller->layout);
        self::assertStringContainsString($rootContainer->name, $response);
        self::assertStringContainsString($childContainer->name, $response);
        self::assertStringContainsString('Выбрать', $response);
    }

    /**
     * GET search-container рендерит результаты поиска только среди контейнеров.
     */
    public function testSearchContainerRendersOnlyMatchingContainers(): void
    {
        [$controller, $repo, , , , $matchingContainer, $nonContainer] = $this->prepareItemListFixture();

        $this->setGetRequest(['q' => 'Кабельный']);

        $response = $controller->actionSearchContainer($repo->id, 'Кабельный');

        self::assertIsString($response);
        self::assertSame('blank', $controller->layout);
        self::assertStringContainsString('Всего найдено контейнеров: 1', $response);
        self::assertStringContainsString($matchingContainer->name, $response);
        self::assertStringNotContainsString($nonContainer->name, $response);
    }

    /**
     * Поиск с позитивным и негативным словом редиректит на единственный найденный предмет.
     */
    public function testSearchRedirectsToSingleMatchedItemByPositiveAndNegativeWords(): void
    {
        [$controller, $repo, $dviItem] = $this->prepareSearchFixture();

        $this->setGetRequest(['q' => 'video -hdmi']);

        $response = $controller->actionSearch($repo->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$dviItem->itemId}",
            $response->headers->get('Location')
        );
        self::assertStringContainsString('q=video+-hdmi', $response->headers->get('Location'));
    }

    /**
     * Подтвержденный импорт создает дочерние предметы с описанием, тегами и флагом контейнера.
     */
    public function testImportConfirmedTextCreatesChildrenWithTagsAndContainerFlag(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $parent = $this->createItem($repo, $user, [
            'name' => 'Большая коробка',
            'isContainer' => true,
        ]);
        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        $this->setPostRequest([
            'text' => implode("\n", [
                'Переходник DVI',
                '!Черный переходник',
                '#video, dvi',
                'Коробка с мелочами',
                '* контейнер: 1',
                '* теги: коробка, мелочи',
            ]),
            'confirm' => '1',
        ]);

        $response = $controller->actionImport($repo->id, $parent->itemId);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);

        $adapter = Item::findOne(['repoId' => $repo->id, 'name' => 'Переходник DVI']);
        $box = Item::findOne(['repoId' => $repo->id, 'name' => 'Коробка с мелочами']);

        self::assertNotNull($adapter);
        self::assertNotNull($box);
        self::assertSame((int) $parent->itemId, (int) $adapter->parentItemId);
        self::assertSame('Черный переходник', $adapter->description);
        self::assertSame(0, (int) $adapter->isContainer);
        self::assertEqualsCanonicalizing(['video', 'dvi'], $adapter->fetchTags());
        self::assertSame(1, (int) $box->isContainer);
        self::assertEqualsCanonicalizing(['коробка', 'мелочи'], $box->fetchTags());
    }

    /**
     * GET view рендерит страницу предмета через read-side сервис без редиректа.
     */
    public function testViewRendersItemPage(): void
    {
        [$controller, $repo, $parent, $item] = $this->prepareItemViewFixture();

        $this->setGetRequest(['q' => 'usb']);

        $response = $controller->actionView($repo->id, $item->itemId);

        self::assertIsString($response);
        self::assertStringContainsString('Просматриваемый предмет', $response);
        self::assertStringContainsString('usb', $response);
    }

    /**
     * JSON-preview возвращает HTML partial с путём предмета.
     */
    public function testJsonPreviewReturnsRenderedItemWithPath(): void
    {
        [$controller, $repo, $parent, $item] = $this->prepareItemViewFixture();

        $response = $controller->actionJsonPreview($repo->id, $item->itemId);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::FORMAT_JSON, $response->format);
        self::assertIsArray($response->data);
        self::assertArrayHasKey('content', $response->data);
        self::assertStringContainsString('Просматриваемый предмет', $response->data['content']);
        self::assertStringContainsString('Контейнер для просмотра', $response->data['content']);
    }

    /**
     * POST create создает предмет и сохраняет теги через общую обработку формы предмета.
     */
    public function testCreatePostCreatesItemWithTags(): void
    {
        [$controller, $repo, $parent] = $this->prepareItemFormFixture();
        $_FILES = [];

        $this->setPostRequest([
            'Item' => [
                'name' => 'Новый предмет',
                'description' => 'Описание нового предмета',
                'parentItemId' => $parent->itemId,
                'isContainer' => '0',
                'priority' => '7',
            ],
            'ItemTagsForm' => [
                'tags' => 'новый, проверка',
            ],
        ]);

        $response = $controller->actionCreate($repo->id, $parent->itemId);

        $item = Item::findOne(['repoId' => $repo->id, 'name' => 'Новый предмет']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotNull($item);
        self::assertStringContainsString("/repo/{$repo->id}/items/{$item->itemId}", $response->headers->get('Location'));
        self::assertSame((int) $parent->itemId, (int) $item->parentItemId);
        self::assertEqualsCanonicalizing(['новый', 'проверка'], $item->fetchTags());
    }

    /**
     * POST update обновляет предмет и заменяет его теги через общую обработку формы предмета.
     */
    public function testUpdatePostUpdatesItemAndReplacesTags(): void
    {
        [$controller, $repo, $parent, $item] = $this->prepareItemFormFixture();
        $item->saveTagsFromString('старый, тег');
        $_FILES = [];

        $this->setPostRequest([
            'Item' => [
                'name' => 'Обновленный предмет',
                'description' => 'Новое описание',
                'parentItemId' => $parent->itemId,
                'isContainer' => '0',
                'priority' => '3',
                'itemId' => $item->itemId,
            ],
            'ItemTagsForm' => [
                'tags' => 'обновленный, тег',
            ],
        ]);

        $response = $controller->actionUpdate($repo->id, $item->itemId);

        $item->refresh();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString("/repo/{$repo->id}/items/{$item->itemId}", $response->headers->get('Location'));
        self::assertSame('Обновленный предмет', $item->name);
        self::assertSame('Новое описание', $item->description);
        self::assertEqualsCanonicalizing(['обновленный', 'тег'], $item->fetchTags());
    }

    /**
     * POST delete без hardDelete мягко удаляет предмет и редиректит к родительскому контейнеру.
     */
    public function testDeletePostSoftDeletesItemAndRedirectsToParent(): void
    {
        [$controller, $repo, $parent, $item] = $this->prepareDeleteFixture();

        $this->setPostRequest([
            'ItemDeleteForm' => [
                'hardDelete' => '0',
            ],
        ]);

        $response = $controller->actionDelete($repo->id, $item->itemId);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$parent->itemId}",
            $response->headers->get('Location')
        );
        self::assertNull(Item::findOne($item->id));

        $deletedItem = Item::findWithDeleted()->where(['id' => $item->id])->one();
        self::assertNotNull($deletedItem);
        self::assertNotNull($deletedItem->deleted);
        self::assertSame((int) Yii::$app->user->id, (int) $deletedItem->deletedBy);
    }

    /**
     * POST delete с hardDelete полностью удаляет корневой предмет и редиректит к списку предметов.
     */
    public function testDeletePostHardDeletesRootItemAndRedirectsToIndex(): void
    {
        [$controller, $repo, $item] = $this->prepareRootDeleteFixture();

        $this->setPostRequest([
            'ItemDeleteForm' => [
                'hardDelete' => '1',
            ],
        ]);

        $response = $controller->actionDelete($repo->id, $item->itemId);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString("/repo/{$repo->id}/items", $response->headers->get('Location'));
        self::assertNull(Item::findWithDeleted()->where(['id' => $item->id])->one());
    }

    /**
     * Создает предметы для проверки read-side списков ItemsController.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item, 3:Item, 4:Item, 5:Item, 6:Item}
     */
    private function prepareItemListFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user, [
            'name' => 'Репозиторий со списком предметов',
        ]);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $rootContainer = $this->createItem($repo, $user, [
            'name' => 'Корневой контейнер для списка',
            'isContainer' => true,
        ]);
        $rootItem = $this->createItem($repo, $user, [
            'name' => 'Корневой предмет для списка',
        ]);
        $childContainer = $this->createItem($repo, $user, [
            'name' => 'Вложенный контейнер для выбора',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => true,
        ]);
        $matchingContainer = $this->createItem($repo, $user, [
            'name' => 'Кабельный контейнер для поиска',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => true,
        ]);
        $nonContainer = $this->createItem($repo, $user, [
            'name' => 'Кабельный предмет не контейнер',
            'parentItemId' => $rootContainer->itemId,
        ]);

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $rootContainer, $rootItem, $childContainer, $matchingContainer, $nonContainer];
    }

    /**
     * Создает минимальный набор предметов для проверки поиска по словам и исключениям.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item}
     */
    private function prepareSearchFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $dviItem = $this->createItem($repo, $user, [
            'name' => 'Переходник DVI',
            'description' => 'Видеоадаптер для монитора',
        ]);
        $dviItem->saveTagsFromString('video, dvi');

        $hdmiItem = $this->createItem($repo, $user, [
            'name' => 'Кабель HDMI',
            'description' => 'Видеокабель',
        ]);
        $hdmiItem->saveTagsFromString('video, hdmi');

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $dviItem];
    }

    /**
     * Создает предмет внутри контейнера для проверки view/json-preview сценариев.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item, 3:Item}
     */
    private function prepareItemViewFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $parent = $this->createItem($repo, $user, [
            'name' => 'Контейнер для просмотра',
            'isContainer' => true,
        ]);
        $item = $this->createItem($repo, $user, [
            'name' => 'Просматриваемый предмет',
            'description' => 'Описание для просмотра',
            'parentItemId' => $parent->itemId,
        ]);

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $parent, $item];
    }

    /**
     * Создает контейнер и предмет для проверки create/update форм.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item, 3:Item}
     */
    private function prepareItemFormFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $parent = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $item = $this->createItem($repo, $user, [
            'name' => 'Редактируемый предмет',
            'parentItemId' => $parent->itemId,
        ]);

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $parent, $item];
    }

    /**
     * Создает дочерний предмет для проверки удаления с возвратом к родителю.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item, 3:Item}
     */
    private function prepareDeleteFixture(): array
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

        $parent = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $item = $this->createItem($repo, $user, [
            'name' => 'Удаляемый предмет',
            'parentItemId' => $parent->itemId,
        ]);

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $parent, $item];
    }

    /**
     * Создает корневой предмет для проверки удаления с возвратом к списку предметов.
     *
     * @return array{0:ItemsController, 1:\common\models\Repo, 2:Item}
     */
    private function prepareRootDeleteFixture(): array
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
            'name' => 'Корневой предмет',
        ]);

        $controller = new ItemsController('items', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $item];
    }
}
