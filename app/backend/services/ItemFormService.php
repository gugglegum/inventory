<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ItemForm;
use backend\models\ItemTagsForm;
use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\Repo;
use common\models\User as Identity;
use yii\web\User;

/**
 * Готовит и сохраняет модель предмета для create/update форм.
 *
 * Сервис содержит настройку сценариев, служебных полей и формы тегов, чтобы ItemsController
 * оставался HTTP-обвязкой вокруг form workflow.
 */
final class ItemFormService
{
    /**
     * Создает новую модель предмета, подготовленную для формы создания.
     *
     * @param Repo $repo Репозиторий, в котором создается предмет.
     * @param ?Item $parent Родительский контейнер, если предмет создается внутри контейнера.
     * @param User<Identity> $user Текущий пользователь, записываемый в createdBy.
     * @param ItemAccessValidator $itemAccessValidator Валидатор прав для сохранения предмета.
     * @param bool $isContainer Начальное значение флага контейнера из query-параметра.
     */
    public function prepareForCreate(
        Repo $repo,
        ?Item $parent,
        User $user,
        ItemAccessValidator $itemAccessValidator,
        bool $isContainer,
    ): ItemForm {
        $item = new Item();
        $item->scenario = Item::SCENARIO_CREATE;
        $item->setItemAccessValidator($itemAccessValidator);
        $item->repoId = $repo->id;
        $item->priority = 0;
        $item->createdBy = (int) $user->id;
        $item->parentItemId = $parent?->itemId;
        $item->isContainer = $isContainer ? 1 : 0;

        return new ItemForm($item);
    }

    /**
     * Подготавливает существующую модель предмета для формы обновления.
     *
     * @param User<Identity> $user Текущий пользователь, записываемый в updatedBy.
     */
    public function prepareForUpdate(Item $item, User $user, ItemAccessValidator $itemAccessValidator): ItemForm
    {
        $item->scenario = Item::SCENARIO_UPDATE;
        $item->setItemAccessValidator($itemAccessValidator);
        $item->updatedBy = (int) $user->id;

        return new ItemForm($item);
    }

    /**
     * Создает форму тегов; для существующего предмета заполняет ее текущими тегами.
     *
     * @throws \yii\db\Exception
     */
    public function createTagsForm(?Item $item = null): ItemTagsForm
    {
        $tagsForm = new ItemTagsForm();
        if ($item !== null) {
            $tagsForm->tags = $item->fetchTagsAsString();
        }

        return $tagsForm;
    }

    /**
     * Загружает POST-данные в форму предмета и сохраняет связанную модель.
     *
     * @throws \yii\db\Exception
     */
    public function save(ItemForm $itemForm, array $postData): bool
    {
        return $itemForm->load($postData) && $itemForm->save();
    }
}
