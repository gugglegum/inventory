<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\services\RepoDeletionService;
use backend\services\RepoFormService;
use common\components\UserAccess;
use common\models\Repo;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * RepoController implements the CRUD actions for Repo model.
 */
class RepoController extends RepoAwareController
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
        $repoFormService = new RepoFormService();
        $repoForm = $repoFormService->prepareForCreate($this->getItemAccessValidator());

        if (Yii::$app->request->isPost) {
            if ($repoFormService->save($repoForm, Yii::$app->request->post())) {
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
        $repoFormService = new RepoFormService();
        $repoForm = $repoFormService->prepareForUpdate($repo, $repoUser);

        if (Yii::$app->request->isPost) {
            if ($repoFormService->save($repoForm, Yii::$app->request->post())) {
                return $this->redirect(['view', 'repoId' => $repo->id]);
            }
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
        $repoDeletionService = new RepoDeletionService();
        if (Yii::$app->request->isPost) {
            if (!$repoDeletionService->delete($repo)) {
                return $this->render('delete', [
                    'repo' => $repo,
                    'affectedUsers' => $repoDeletionService->getAffectedUsers($repo, $this->getLoggedUser()),
                ]);
            }
            return $this->redirect(['repo/index', 'repoId' => $repo->id]);
        } else {
            return $this->render('delete', [
                'repo' => $repo,
                'affectedUsers' => $repoDeletionService->getAffectedUsers($repo, $this->getLoggedUser()),
            ]);
        }
    }

}
