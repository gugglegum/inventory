<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\RepoForm;
use common\components\ItemAccessValidator;
use common\models\Repo;
use common\models\RepoUser;

/**
 * Готовит и сохраняет форму репозитория.
 *
 * Сервис отделяет сценарии create/update и начальное заполнение RepoForm от HTTP-логики RepoController.
 */
final class RepoFormService
{
    /**
     * Создает форму для нового репозитория.
     */
    public function prepareForCreate(ItemAccessValidator $itemAccessValidator): RepoForm
    {
        $repoForm = new RepoForm();
        $repoForm->scenario = RepoForm::SCENARIO_CREATE;
        $repoForm->setRepo(new Repo()->setItemAccessValidator($itemAccessValidator));
        $repoForm->setRepoUser(new RepoUser());

        return $repoForm;
    }

    /**
     * Создает форму редактирования и заполняет ее текущими значениями репозитория и персональных настроек.
     */
    public function prepareForUpdate(Repo $repo, RepoUser $repoUser): RepoForm
    {
        $repoForm = new RepoForm();
        $repoForm->scenario = RepoForm::SCENARIO_UPDATE;
        $repoForm->setRepo($repo);
        $repoForm->setRepoUser($repoUser);
        $repoForm->fillFromModels();

        return $repoForm;
    }

    /**
     * Загружает POST-данные и сохраняет форму репозитория.
     *
     * @throws \yii\db\Exception
     */
    public function save(RepoForm $repoForm, array $postData): bool
    {
        return $repoForm->load($postData) && $repoForm->save();
    }
}
