<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\PhotoController;
use backend\services\PhotoAttachmentService;
use common\models\Item;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Integration-тесты HTTP-сценариев PhotoController.
 *
 * Проверяют управление фотографиями предметов и заметок через POST endpoints.
 */
final class PhotoControllerTest extends DbTestCase
{
    /**
     * Оригинал доступной фотографии передается nginx через внутренний URI.
     */
    public function testViewServesAccessibleItemPhotoThroughXAccelRedirect(): void
    {
        [$controller, $item] = $this->prepareFixture();
        $itemPhoto = $this->createItemPhoto($item);

        $response = $controller->actionView((int) $itemPhoto->photoId);

        self::assertSame(200, $response->statusCode);
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertSame(
            '/_protected-photos/' . Photo::getFileRelativePath((int) $itemPhoto->photoId),
            $response->headers->get('X-Accel-Redirect')
        );
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertSame('', $response->content);
    }

    /**
     * Фотография заметки использует права того же репозитория, что и предмет заметки.
     */
    public function testViewServesAccessiblePostPhotoThroughXAccelRedirect(): void
    {
        [$controller, , $post] = $this->prepareFixture();
        $postPhoto = $this->createPostPhoto($post);

        $response = $controller->actionView((int) $postPhoto->photoId);

        self::assertSame(200, $response->statusCode);
        self::assertSame(
            '/_protected-photos/' . Photo::getFileRelativePath((int) $postPhoto->photoId),
            $response->headers->get('X-Accel-Redirect')
        );
    }

    /**
     * Сам факт авторизации не дает доступ к фотографии чужого репозитория.
     */
    public function testViewRejectsUserWithoutRepoAccess(): void
    {
        [$controller, $item] = $this->prepareFixture();
        $itemPhoto = $this->createItemPhoto($item);
        $this->login($this->createUser());

        $this->expectException(ForbiddenHttpException::class);

        $controller->actionView((int) $itemPhoto->photoId);
    }

    /**
     * Миниатюра генерируется при необходимости и также передается только через internal location.
     */
    public function testThumbnailServesAccessiblePhotoThroughXAccelRedirect(): void
    {
        [$controller, $item] = $this->prepareFixture();
        $itemPhoto = $this->createItemPhoto($item);

        $response = $controller->actionThumbnail((int) $itemPhoto->photoId, 48, 48, true, true, 90);

        self::assertSame(200, $response->statusCode);
        self::assertSame(
            '/_protected-thumbnails/' . Photo::getThumbnailFileRelativePath(
                (int) $itemPhoto->photoId,
                48,
                48,
                true,
                true,
                90
            ),
            $response->headers->get('X-Accel-Redirect')
        );
        self::assertFileExists(
            Photo::getThumbnailFileById((int) $itemPhoto->photoId, 48, 48, true, true, 90)
        );
    }

    /**
     * HTML-ссылки всегда ведут в PHP-контроллер, даже когда thumbnail уже есть в кэше.
     */
    public function testPhotoUrlsPointToProtectedControllerActions(): void
    {
        [, $item] = $this->prepareFixture();
        $photo = $this->createItemPhoto($item)->photo;
        $photo->createThumbnail(48, 48, true, true, 90);
        Yii::$app->request->setBaseUrl('');

        self::assertSame('/photo/' . $photo->id . '.jpg', $photo->getUrl());

        $thumbnailUrl = $photo->getThumbnailUrl(48, 48, true, true, 90);
        self::assertSame('/photo/' . $photo->id . '/thumbnail', parse_url($thumbnailUrl, PHP_URL_PATH));
        parse_str((string) parse_url($thumbnailUrl, PHP_URL_QUERY), $query);
        self::assertSame(
            [
                'width' => '48',
                'height' => '48',
                'upscale' => '1',
                'crop' => '1',
                'quality' => '90',
            ],
            $query
        );
    }

    /**
     * sort-up без photoType сохраняет старое поведение для фотографий предметов.
     */
    public function testSortUpDefaultsToItemPhotoType(): void
    {
        [$controller, $item] = $this->prepareFixture();
        $firstPhoto = $this->createItemPhoto($item);
        $secondPhoto = $this->createItemPhoto($item);

        $this->setPostRequest([
            'id' => $secondPhoto->id,
        ]);

        $controller->actionSortUp();

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * sort-down с photoType=post меняет порядок фотографий заметки.
     */
    public function testSortDownSupportsPostPhotoType(): void
    {
        [$controller, , $post] = $this->prepareFixture();
        $firstPhoto = $this->createPostPhoto($post);
        $secondPhoto = $this->createPostPhoto($post);

        $this->setPostRequest([
            'id' => $firstPhoto->id,
            'photoType' => PhotoAttachmentService::TYPE_POST,
        ]);

        $controller->actionSortDown();

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * delete с photoType=post удаляет фотографию заметки.
     */
    public function testDeleteSupportsPostPhotoType(): void
    {
        [$controller, , $post] = $this->prepareFixture();
        $postPhoto = $this->createPostPhoto($post);

        $this->setPostRequest([
            'id' => $postPhoto->id,
            'photoType' => PhotoAttachmentService::TYPE_POST,
        ]);

        $controller->actionDelete();

        self::assertNull(PostPhoto::findOne($postPhoto->id));
    }

    /**
     * Создает контроллер, предмет и заметку для HTTP-сценариев фотографий.
     *
     * @return array{0:PhotoController, 1:Item, 2:Post}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет с фотографиями',
        ]);
        $post = $this->createPost($item, $user, [
            'title' => 'Заметка с фотографиями',
        ]);

        $controller = new PhotoController('photo', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $item, $post];
    }
}
