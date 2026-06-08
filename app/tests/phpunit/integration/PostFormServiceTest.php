<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\PostFormService;
use common\models\Item;
use common\models\Post;
use common\models\PostPhoto;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use DateTimeImmutable;
use DateTimeZone;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса подготовки и сохранения формы заметки.
 *
 * Проверяют create/update сценарии Post и прикрепление фотографий без HTTP-обвязки PostsController.
 */
final class PostFormServiceTest extends DbTestCase
{
    /**
     * prepareForCreate() выставляет служебные поля, а save() создает заметку и прикрепляет JPEG.
     */
    public function testPrepareForCreateAndSaveCreatesPostWithUploadedPhoto(): void
    {
        [, $item] = $this->prepareFixture();
        $uploadedFile = $this->createUploadedJpegFixture();
        $service = new PostFormService();

        $post = $service->prepareForCreate($item, Yii::$app->getUser());
        $result = $service->save(
            $post,
            [
                'Post' => [
                    'datetimeText' => '01.06.2026 12:30',
                    'title' => 'Новая заметка',
                    'text' => 'Текст новой заметки',
                ],
            ],
            [
                'photos' => [
                    'tmp_name' => [
                        $uploadedFile,
                        '',
                    ],
                ],
            ],
        );

        @unlink($uploadedFile);

        /** @var PostPhoto|null $postPhoto */
        $postPhoto = $post->getPostPhotos()->one();

        self::assertTrue($result);
        self::assertFalse($post->isNewRecord);
        self::assertSame(Post::SCENARIO_CREATE, $post->scenario);
        self::assertSame((int) $item->id, (int) $post->itemId);
        self::assertSame((int) Yii::$app->user->id, (int) $post->createdBy);
        self::assertSame('Новая заметка', $post->title);
        self::assertSame('Текст новой заметки', $post->text);
        self::assertSame($this->timestamp('01.06.2026 12:30'), (int) $post->datetime);
        self::assertNotNull($postPhoto);
        self::assertNotNull($postPhoto->photo);
        self::assertFileExists($postPhoto->photo->getFile());
        self::assertSame(0, (int) $postPhoto->sortIndex);
    }

    /**
     * prepareForUpdate() выставляет update-сценарий и updatedBy, а save() обновляет заметку.
     */
    public function testPrepareForUpdateAndSaveUpdatesPost(): void
    {
        [, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Старая заметка',
            'text' => 'Старый текст',
        ]);
        $service = new PostFormService();

        $preparedPost = $service->prepareForUpdate($post, Yii::$app->getUser());
        $result = $service->save(
            $preparedPost,
            [
                'Post' => [
                    'datetimeText' => '02.06.2026 08:15',
                    'title' => 'Обновленная заметка',
                    'text' => 'Новый текст',
                ],
            ],
            [],
        );

        self::assertTrue($result);
        self::assertSame(Post::SCENARIO_UPDATE, $preparedPost->scenario);
        self::assertSame((int) Yii::$app->user->id, (int) $preparedPost->updatedBy);
        self::assertSame('Обновленная заметка', $preparedPost->title);
        self::assertSame('Новый текст', $preparedPost->text);
        self::assertSame($this->timestamp('02.06.2026 08:15'), (int) $preparedPost->datetime);
    }

    /**
     * Создает репозиторий и предмет для сервисных сценариев заметок.
     *
     * @return array{0:Repo, 1:Item, 2:User}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет с заметками',
        ]);

        return [$repo, $item, $user];
    }

    /**
     * Возвращает unix timestamp для пользовательского текста даты в timezone приложения.
     */
    private function timestamp(string $datetimeText): int
    {
        $timezone = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');

        return DateTimeImmutable::createFromFormat('d.m.Y H:i', $datetimeText, $timezone)->getTimestamp();
    }
}
