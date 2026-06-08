<?php

declare(strict_types=1);

namespace tests\phpunit;

use common\components\ItemAccessValidator;
use common\models\Inventory;
use common\models\InventoryItem;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Transaction;

/**
 * Базовый класс integration-тестов, работающих с тестовой базой данных.
 *
 * Каждый тест запускается внутри транзакции, которая откатывается в tearDown().
 * Хелперы создают минимальные валидные сущности проекта и сразу проверяют успешность сохранения.
 */
abstract class DbTestCase extends TestCase
{
    /**
     * Транзакция текущего теста, откатываемая после завершения сценария.
     */
    private ?Transaction $transaction = null;

    /**
     * Открывает транзакцию тестовой БД перед сценарием.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = Yii::$app->db->beginTransaction();
    }

    /**
     * Откатывает изменения теста и уничтожает Yii-приложение.
     */
    protected function tearDown(): void
    {
        if ($this->transaction !== null && $this->transaction->isActive) {
            $this->transaction->rollBack();
        }
        $this->transaction = null;
        parent::tearDown();
    }

    /**
     * Создает пользователя с рабочим паролем и authKey.
     *
     * @param array{username?:string, email?:string, access?:int, password?:string} $attributes Переопределяемые поля пользователя.
     */
    protected function createUser(array $attributes = []): User
    {
        $user = new User();
        $user->username = $attributes['username'] ?? 'user_' . bin2hex(random_bytes(4));
        $user->email = $attributes['email'] ?? $user->username . '@example.test';
        $user->access = $attributes['access'] ?? 0;
        $user->setPassword($attributes['password'] ?? 'password');
        $user->generateAuthKey();

        $this->saveModel($user);

        return $user;
    }

    /**
     * Авторизует пользователя в тестовом Yii-приложении.
     */
    protected function login(User $user): void
    {
        Yii::$app->user->login($user);
    }

    /**
     * Создает репозиторий от имени пользователя и авторизует его перед сохранением.
     *
     * @param array{name?:string, description?:?string, lastItemId?:int} $attributes Переопределяемые поля репозитория.
     */
    protected function createRepo(User $user, array $attributes = []): Repo
    {
        $this->login($user);

        $repo = new Repo([
            'name' => $attributes['name'] ?? 'Тестовый репозиторий',
            'description' => $attributes['description'] ?? null,
            'lastItemId' => $attributes['lastItemId'] ?? 0,
            'createdBy' => $user->id,
        ]);

        $this->saveModel($repo);

        return $repo;
    }

    /**
     * Выдает пользователю права на репозиторий.
     *
     * @param int $access Битовая маска прав из RepoUser::ACCESS_*.
     */
    protected function grantRepoAccess(Repo $repo, User $user, int $access): RepoUser
    {
        $repoUser = new RepoUser([
            'repoId' => $repo->id,
            'userId' => $user->id,
            'access' => $access,
            'priority' => 0,
        ]);

        $this->saveModel($repoUser);

        return $repoUser;
    }

    /**
     * Создает предмет в репозитории с валидатором прав и repo-scoped itemId.
     *
     * @param array{parentItemId?:?int, name?:string, description?:?string, isContainer?:bool|int, priority?:int} $attributes Переопределяемые поля предмета.
     */
    protected function createItem(Repo $repo, User $user, array $attributes = []): Item
    {
        $this->login($user);

        $item = new Item();
        $item->scenario = Item::SCENARIO_CREATE;
        $item->setItemAccessValidator(new ItemAccessValidator());
        $item->repoId = $repo->id;
        $item->parentItemId = $attributes['parentItemId'] ?? null;
        $item->name = $attributes['name'] ?? 'Тестовый предмет';
        $item->description = $attributes['description'] ?? null;
        $item->isContainer = (int) ($attributes['isContainer'] ?? 0);
        $item->priority = $attributes['priority'] ?? 0;
        $item->createdBy = $user->id;

        $this->saveModel($item);

        return $item;
    }

    /**
     * Создает заметку к предмету.
     *
     * @param array{datetimeText?:string, title?:string, text?:?string} $attributes Переопределяемые поля заметки.
     */
    protected function createPost(Item $item, User $user, array $attributes = []): Post
    {
        $this->login($user);

        $post = new Post();
        $post->scenario = Post::SCENARIO_CREATE;
        $post->itemId = $item->id;
        $post->datetimeText = $attributes['datetimeText'] ?? '01.06.2026 12:30';
        $post->title = $attributes['title'] ?? 'Тестовая заметка';
        $post->text = $attributes['text'] ?? 'Текст тестовой заметки';
        $post->createdBy = $user->id;

        $this->saveModel($post);

        return $post;
    }

    /**
     * Создает маленький JPEG-файл, имитирующий загруженное пользователем фото.
     */
    protected function createUploadedJpegFixture(): string
    {
        $this->ensurePhotoRuntimeDirectories();

        $file = tempnam(Yii::$app->params['photos']['storageTemp'], 'upload');
        self::assertIsString($file, 'Не удалось создать временный файл для фотофикстуры.');

        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image, 'Не удалось создать тестовое JPEG-изображение.');

        $color = imagecolorallocate($image, 80, 120, 160);
        self::assertIsInt($color, 'Не удалось выделить цвет для тестового JPEG-изображения.');

        imagefill($image, 0, 0, $color);
        self::assertTrue(imagejpeg($image, $file), 'Не удалось записать тестовое JPEG-изображение.');
        imagedestroy($image);

        return $file;
    }

    /**
     * Создает сохраненную фотографию из маленького JPEG-файла.
     */
    protected function createPhoto(): Photo
    {
        $uploadedFile = $this->createUploadedJpegFixture();

        $photo = new Photo();
        $photo->assignFile($uploadedFile);
        $this->saveModel($photo);
        @unlink($uploadedFile);

        return $photo;
    }

    /**
     * Создает связь фотографии с предметом.
     */
    protected function createItemPhoto(Item $item): ItemPhoto
    {
        $itemPhoto = new ItemPhoto([
            'itemId' => $item->id,
            'photoId' => $this->createPhoto()->id,
        ]);

        $this->saveModel($itemPhoto);

        return $itemPhoto;
    }

    /**
     * Создает связь фотографии с заметкой.
     */
    protected function createPostPhoto(Post $post): PostPhoto
    {
        $postPhoto = new PostPhoto([
            'postId' => $post->id,
            'photoId' => $this->createPhoto()->id,
        ]);

        $this->saveModel($postPhoto);

        return $postPhoto;
    }

    /**
     * Создает runtime-каталоги, нужные для сохранения фотографий и миниатюр в тестах.
     */
    protected function ensurePhotoRuntimeDirectories(): void
    {
        foreach ([
            Yii::$app->params['photos']['storagePath'],
            Yii::$app->params['photos']['storageTemp'],
            Yii::$app->params['photos']['thumbnailPath'],
            Yii::$app->params['photos']['thumbnailTemp'],
        ] as $directory) {
            if (!is_dir($directory)) {
                self::assertTrue(
                    mkdir($directory, 0777, true),
                    "Не удалось создать runtime-каталог {$directory}."
                );
            }
        }
    }

    /**
     * Создает инвентаризацию для контейнера.
     *
     * @param array{status?:int, closedBy?:?int, closed?:?int} $attributes Переопределяемые поля инвентаризации.
     */
    protected function createInventory(Item $container, User $user, array $attributes = []): Inventory
    {
        $inventory = new Inventory([
            'containerId' => $container->id,
            'status' => $attributes['status'] ?? Inventory::STATUS_OPENED,
            'createdBy' => $user->id,
            'closedBy' => $attributes['closedBy'] ?? null,
            'closed' => $attributes['closed'] ?? null,
        ]);

        $this->saveModel($inventory);

        return $inventory;
    }

    /**
     * Создает отметку о подтвержденном предмете внутри инвентаризации.
     */
    protected function createInventoryItem(Inventory $inventory, Item $item, User $user): InventoryItem
    {
        $inventoryItem = new InventoryItem([
            'inventoryId' => $inventory->id,
            'itemId' => $item->id,
            'createdBy' => $user->id,
        ]);

        $this->saveModel($inventoryItem);

        return $inventoryItem;
    }

    /**
     * Сохраняет ActiveRecord и сразу проваливает тест с ошибками модели, если сохранение не удалось.
     */
    protected function saveModel(ActiveRecord $model): void
    {
        self::assertTrue(
            $model->save(),
            get_class($model) . ' save failed: ' . (json_encode($model->getErrors(), JSON_UNESCAPED_UNICODE) ?: '')
        );
    }
}
