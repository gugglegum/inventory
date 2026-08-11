<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\PostForm;
use common\models\Item;
use common\models\Post;
use common\models\User as Identity;
use DateTimeImmutable;
use DateTimeZone;
use yii\base\Exception;
use yii\web\User;

/**
 * Готовит и сохраняет форму заметки к предмету.
 *
 * Сервис отделяет сценарии create/update и служебные поля автора от HTTP-логики PostsController.
 */
final class PostFormService
{
    /**
     * Создает новую модель заметки с полями, нужными форме создания.
     *
     * @param User<Identity> $user Текущий пользователь, записываемый в createdBy.
     */
    public function prepareForCreate(Item $item, User $user): PostForm
    {
        $post = new Post();
        $post->scenario = Post::SCENARIO_CREATE;
        $post->itemId = $item->id;
        $post->createdBy = (int) $user->id;

        return new PostForm($post, new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('d.m.Y H:i'));
    }

    /**
     * Переводит существующую заметку в update-сценарий и записывает автора изменения.
     *
     * @param User<Identity> $user Текущий пользователь, записываемый в updatedBy.
     */
    public function prepareForUpdate(Post $post, User $user): PostForm
    {
        $post->scenario = Post::SCENARIO_UPDATE;
        $post->updatedBy = (int) $user->id;

        return new PostForm($post);
    }

    /**
     * Загружает POST-данные и сохраняет заметку.
     *
     * @param PostForm $postForm Форма, подготовленная для create или update.
     * @param array $postData POST-данные текущего запроса.
     * @throws Exception
     */
    public function save(PostForm $postForm, array $postData): bool
    {
        if (!$postForm->load($postData) || !$postForm->save()) {
            return false;
        }

        return true;
    }
}
