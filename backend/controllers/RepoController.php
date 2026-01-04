<?php

declare(strict_types=1);

namespace backend\controllers;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Repo;
use common\models\RepoUser;
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
            ->orderBy(['priority' => SORT_DESC, 'id' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'repos' => $repos,
        ]);
    }

    /**
     * @return Response|string
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function actionCreate(): Response|string
    {
        $repo = new Repo();
        $repo->scenario = Repo::SCENARIO_CREATE;
        $repo->setItemAccessValidator($this->getItemAccessValidator());
        $repo->priority = 0;
        $repo->lastItemId = 0;
        $repo->createdBy = $this->getLoggedUser()->id;

        if (Yii::$app->request->isPost) {
            if ($repo->load(Yii::$app->request->post()) && $repo->save()) {

                $repoUser = new RepoUser();
                $repoUser->load([
                    'repoId' => $repo->id,
                    'userId' => $repo->createdBy,
                    'access' => RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS | RepoUser::ACCESS_EDIT_REPO | RepoUser::ACCESS_DELETE_REPO,
                ], '');
                if (!$repoUser->save()) {
                    throw new Exception(ValidateErrorsFormatter::getMessage($repoUser));
                }

                return $this->redirect(['repo/index']);
            }
        }
        return $this->render('create', [
            'repo' => $repo,
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
        $repo->scenario = Repo::SCENARIO_UPDATE;
        $repo->setItemAccessValidator($this->getItemAccessValidator());
        $repo->updatedBy = $this->getLoggedUser()->id;
        if (Yii::$app->request->isPost) {
            if ($repo->load(Yii::$app->request->post()) && $repo->save()) {
                return $this->redirect(['index', 'repoId' => $repo->id]);
            }
        }
        return $this->render('update', [
            'repo' => $repo,
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
        $repo->setItemAccessValidator($this->getItemAccessValidator());
        if (Yii::$app->request->isPost) {
            if ($repo->delete() === false) {
                return $this->render('delete', [
                    'repo' => $repo,
                ]);
            }
            return $this->redirect(['repo/index', 'repoId' => $repo->id]);
        } else {
            return $this->render('delete', [
                'repo' => $repo,
            ]);
        }
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
