<?php

declare(strict_types=1);

namespace backend\controllers;

use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User as Identity;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\User as WebUser;

/**
 * Базовый контроллер для HTTP-сценариев, работающих внутри репозитория.
 *
 * Инкапсулирует повторяющийся поиск репозитория, предметов и пользовательских прав,
 * чтобы CRUD-контроллеры не дублировали одинаковую access/context-обвязку.
 */
abstract class RepoAwareController extends Controller
{
    /**
     * Находит репозиторий и проверяет доступ текущего пользователя.
     *
     * @param int $repoId ID репозитория.
     * @param int $accessType Битовая маска требуемых прав из RepoUser::ACCESS_*.
     * @return Repo Репозиторий с привязанным ItemAccessValidator.
     * @throws ForbiddenHttpException Если у пользователя нет доступа или нужных прав.
     * @throws NotFoundHttpException Если репозиторий не найден.
     */
    protected function findRepo(int $repoId, int $accessType = 0): Repo
    {
        $repo = Repo::findOne($repoId);
        if ($repo === null) {
            throw new NotFoundHttpException("Запрошенный репозиторий {$repoId} не существует");
        }

        $accessValidator = $this->getItemAccessValidator();
        $repo->setItemAccessValidator($accessValidator);

        if (!$accessValidator->hasUserAccessToRepo($repo, $accessType)) {
            throw new ForbiddenHttpException("У вас нет доступа к репозиторию {$repoId} или достаточных прав на выполнение данной операции");
        }

        return $repo;
    }

    /**
     * Находит предмет по внутреннему ID репозитория и repo-scoped itemId.
     *
     * @param int $repoId ID репозитория.
     * @param int $itemId Номер предмета внутри репозитория.
     * @return Item Предмет с привязанным ItemAccessValidator.
     * @throws NotFoundHttpException Если предмет не найден.
     */
    protected function findItem(int $repoId, int $itemId): Item
    {
        return $this->findItemByRepoScopedId(
            $repoId,
            $itemId,
            "Запрошенный предмет {$repoId}#{$itemId} не существует"
        );
    }

    /**
     * Находит родительский контейнер по внутреннему ID репозитория и repo-scoped itemId.
     *
     * @param int $repoId ID репозитория.
     * @param int $parentItemId Номер родительского контейнера внутри репозитория.
     * @return Item Контейнер с привязанным ItemAccessValidator.
     * @throws NotFoundHttpException Если контейнер не найден.
     */
    protected function findParentItem(int $repoId, int $parentItemId): Item
    {
        return $this->findItemByRepoScopedId(
            $repoId,
            $parentItemId,
            "Родительский контейнер {$repoId}#{$parentItemId} не существует"
        );
    }

    /**
     * Находит настройки доступа текущего пользователя к репозиторию.
     *
     * @throws ForbiddenHttpException Если связь repo_user неожиданно отсутствует.
     */
    protected function findRepoUser(Repo $repo): RepoUser
    {
        $repoUser = $repo
            ->getRepoUsers()
            ->andWhere(['userId' => $this->getLoggedUser()->id])
            ->one();

        if ($repoUser instanceof RepoUser) {
            return $repoUser;
        }

        throw new ForbiddenHttpException("У вас нет доступа к репозиторию {$repo->id} или достаточных прав на выполнение данной операции");
    }

    /**
     * Создает валидатор доступа, привязанный к текущему Yii user-компоненту.
     */
    protected function getItemAccessValidator(): ItemAccessValidator
    {
        return new ItemAccessValidator()->setUser($this->getLoggedUser());
    }

    /**
     * Возвращает текущий Yii user-компонент.
     *
     * @return WebUser<Identity>
     */
    protected function getLoggedUser(): WebUser
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation
         * @phpstan-var WebUser<Identity> $user
         */
        $user = Yii::$app->getUser();
        return $user;
    }

    /**
     * Находит предмет или контейнер и прикрепляет к нему валидатор доступа.
     *
     * @param int $repoId ID репозитория.
     * @param int $itemId Номер предмета внутри репозитория.
     * @param string $notFoundMessage Сообщение для 404-ошибки.
     * @return Item Предмет с привязанным ItemAccessValidator.
     * @throws NotFoundHttpException Если предмет не найден.
     */
    private function findItemByRepoScopedId(int $repoId, int $itemId, string $notFoundMessage): Item
    {
        $item = Item::findOne(['repoId' => $repoId, 'itemId' => $itemId]);
        if ($item === null) {
            throw new NotFoundHttpException($notFoundMessage);
        }

        $item->setItemAccessValidator($this->getItemAccessValidator());

        return $item;
    }
}
