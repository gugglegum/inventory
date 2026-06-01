<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\RepoForm;
use common\components\ItemAccessValidator;
use common\components\UserAccess;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * RepoController implements the CRUD actions for Repo model.
 */
class RepoController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // create: только если есть право
                    [
                        'allow' => true,
                        'actions' => ['create'],
                        'roles' => ['@'],
                        'matchCallback' => static fn() => UserAccess::canCreateRepo(),
                    ],
                    [
                        'allow' => false,                 // <-- явный запрет
                        'actions' => ['create'],
                        'roles' => ['@'],
                    ],
                    // остальные экшены: просто залогинен
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['get'],
                    'view' => ['get'],
                    'create' => ['get', 'post'],
                    'update' => ['get', 'post'],
                    'delete' => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Item models.
     * @return Response|string
     */
    public function actionIndex(): Response|string
    {
        $repos = Repo::find()
            ->innerJoinWith('repoUsers')
            ->where(['repo_user.userId' => Yii::$app->getUser()->id])
            ->orderBy(['repo_user.priority' => SORT_DESC, 'id' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'repos' => $repos,
        ]);
    }

    /**
     * @param int $repoId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionView(int $repoId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $repoUser = $this->findRepoUser($repo);
        return $this->render('view', [
            'repo' => $repo,
            'repoUser' => $repoUser,
        ]);
    }

    /**
     * @return Response|string
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function actionCreate(): Response|string
    {
        $repoForm = new RepoForm();
        $repoForm->scenario = RepoForm::SCENARIO_CREATE;
        $repoForm->setRepo(new Repo()->setItemAccessValidator($this->getItemAccessValidator()));
        $repoForm->setRepoUser(new RepoUser());

        if (Yii::$app->request->isPost) {
            if ($repoForm->load(Yii::$app->request->post()) && $repoForm->save()) {
                return $this->redirect(['repo/index']);
            }
        }
        return $this->render('create', [
            'repoForm' => $repoForm,
        ]);
    }

    /**
     * @param int $repoId
     * @return Response|string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionUpdate(int $repoId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $repoUser = $this->findRepoUser($repo);

        $repoForm = new RepoForm();
        $repoForm->scenario = RepoForm::SCENARIO_UPDATE;
        $repoForm->setRepo($repo);
        $repoForm->setRepoUser($repoUser);

        if (Yii::$app->request->isPost) {
            if ($repoForm->load(Yii::$app->request->post()) && $repoForm->save()) {
                return $this->redirect(['view', 'repoId' => $repo->id]);
            }
        } else {
            $repoForm->load(array_merge($repo->attributes, $repoUser->attributes), '');
        }
        return $this->render('update', [
            'repo' => $repo,
            'repoForm' => $repoForm,
        ]);
    }

    /**
     * @param int $repoId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(int $repoId): Response|string
    {
        $repo = $this->findRepo($repoId);
        if (Yii::$app->request->isPost) {
            if ($repo->delete() === false) {
                return $this->render('delete', [
                    'repo' => $repo,
                    'affectedUsers' => $this->getAffectedUsers($repo),
                ]);
            }
            return $this->redirect(['repo/index', 'repoId' => $repo->id]);
        } else {
            return $this->render('delete', [
                'repo' => $repo,
                'affectedUsers' => $this->getAffectedUsers($repo),
            ]);
        }
    }

    /**
     * Список пользователей, которые могут пострадать при удалении репозитория (кроме текущего пользователя).
     * @return User[]
     */
    private function getAffectedUsers(Repo $repo): array
    {
        $affectedUsers = [];
        foreach ($repo->getRepoUsers()->innerJoinWith('user')->where(['user.status' => \common\models\User::STATUS_ACTIVE])->each() as $repoUser) {
            if ($repoUser->userId !== $this->getLoggedUser()->id) {
                $affectedUsers[] = $repoUser->user;
            }
        }
        return $affectedUsers;
    }

    /**
     * todo: это копия из ItemsController -- вынести это куда-то.
     * @param int $repoId
     * @param int $accessType
     * @return Repo
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    private function findRepo(int $repoId, int $accessType = 0): Repo
    {
        if (($repo = Repo::findOne($repoId)) !== null) {
            $repo->setItemAccessValidator($this->getItemAccessValidator());
            if (new ItemAccessValidator()->hasUserAccessToRepo($repo, $accessType)) {
                return $repo;
            } else {
                throw new ForbiddenHttpException("У вас нет доступа к репозиторию {$repoId} или достаточных прав на выполнение данной операции");
            }
        } else {
            throw new NotFoundHttpException("Запрошенный репозиторий {$repoId} не существует");
        }
    }

    /**
     * @param Repo $repo
     * @return RepoUser
     */
    private function findRepoUser(Repo $repo): RepoUser
    {
        /** @var RepoUser $repoUser */
        $repoUser = $repo->getRepoUsers()->andWhere(['userId' => Yii::$app->getUser()->id])->one();
        return $repoUser;
    }

    /**
     * todo: это копия из ItemsController -- вынести это куда-то.
     * @return ItemAccessValidator
     */
    private function getItemAccessValidator(): ItemAccessValidator
    {
        return new ItemAccessValidator()->setUser($this->getLoggedUser());
    }

    /**
     * todo: это копия из ItemsController -- вынести это куда-то.
     * @return \yii\web\User
     */
    private function getLoggedUser(): \yii\web\User
    {
        return Yii::$app->getUser();
    }
}
