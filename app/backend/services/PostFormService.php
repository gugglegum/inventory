<?php

declare(strict_types=1);

namespace backend\services;

use common\helpers\ValidateErrorsFormatter;
use common\models\Item;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\User as Identity;
use DateTimeImmutable;
use DateTimeZone;
use yii\base\Exception;
use yii\web\User;

/**
 * Готовит и сохраняет форму заметки к предмету.
 *
 * Сервис отделяет сценарии create/update, служебные поля автора и прикрепление новых фотографий
 * от HTTP-логики PostsController.
 */
final class PostFormService
{
    /**
     * Создает новую модель заметки с полями, нужными форме создания.
     *
     * @param User<Identity> $user Текущий пользователь, записываемый в createdBy.
     */
    public function prepareForCreate(Item $item, User $user): Post
    {
        $post = new Post();
        $post->scenario = Post::SCENARIO_CREATE;
        $post->itemId = $item->id;
        $post->createdBy = (int) $user->id;
        $post->datetimeText = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('d.m.Y H:i');

        return $post;
    }

    /**
     * Переводит существующую заметку в update-сценарий и записывает автора изменения.
     *
     * @param User<Identity> $user Текущий пользователь, записываемый в updatedBy.
     */
    public function prepareForUpdate(Post $post, User $user): Post
    {
        $post->scenario = Post::SCENARIO_UPDATE;
        $post->updatedBy = (int) $user->id;

        return $post;
    }

    /**
     * Загружает POST-данные, сохраняет заметку и прикрепляет новые фотографии.
     *
     * @param Post $post Заметка, подготовленная для create или update.
     * @param array $postData POST-данные текущего запроса.
     * @param array $filesData FILES-данные текущего запроса.
     *
     * @throws Exception
     */
    public function save(Post $post, array $postData, array $filesData): bool
    {
        if (!$post->load($postData) || !$post->save()) {
            return false;
        }

        $this->attachPhotos($post, $filesData['photos']['tmp_name'] ?? []);

        return true;
    }

    /**
     * Создает Photo и PostPhoto для каждого реально загруженного файла.
     *
     * @param mixed $tmpNames Значение `$_FILES['photos']['tmp_name']`; ожидается массив путей.
     *
     * @throws Exception
     */
    private function attachPhotos(Post $post, mixed $tmpNames): void
    {
        if (!is_array($tmpNames)) {
            return;
        }

        foreach ($tmpNames as $tmpName) {
            if (!is_string($tmpName) || $tmpName === '') {
                continue;
            }

            $photo = new Photo();
            $photo->assignFile($tmpName);
            if (!$photo->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($photo));
            }

            $postPhoto = new PostPhoto([
                'postId' => $post->id,
                'photoId' => $photo->id,
            ]);
            if (!$postPhoto->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($postPhoto));
            }
        }
    }
}
