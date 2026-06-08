<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\UserForm;
use common\models\User;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Готовит и сохраняет форму создания/редактирования пользователя.
 *
 * Сервис убирает повторяющуюся сборку UserForm из UsersController, оставляя контроллеру
 * только HTTP-решения: прочитать запрос, выполнить redirect или render.
 */
final class UserFormService
{
    /**
     * Создает форму для нового пользователя.
     */
    public function prepareForCreate(): UserForm
    {
        $form = new UserForm();
        $form->scenario = UserForm::SCENARIO_CREATE;
        $form->setUser(new User());

        return $form;
    }

    /**
     * Создает форму редактирования существующего пользователя.
     */
    public function prepareForUpdate(User $user): UserForm
    {
        $form = new UserForm();
        $form->setUser($user);

        return $form;
    }

    /**
     * Загружает данные запроса в форму и сохраняет связанную модель пользователя.
     *
     * @param array $postData POST-данные Yii request.
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function save(UserForm $form, array $postData): bool
    {
        return $form->load($postData) && $form->save();
    }
}
