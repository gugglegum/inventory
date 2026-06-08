<?php

namespace tests\phpunit\integration;

use backend\controllers\ItemsController;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

final class ItemsControllerTest extends DbTestCase
{
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
}
