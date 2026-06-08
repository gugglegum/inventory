<?php

declare(strict_types=1);
namespace backend\controllers;

use backend\services\PostDeletionService;
use backend\services\PostFormService;
use common\components\ItemAccessValidator;
use common\models\Post;
use common\models\Repo;
use Yii;
use common\models\Item;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * PostsController implements the CRUD actions for Post model.
 */
class PostsController extends Controller
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

//    /**
//     * Lists all Item models.
//     * @param int $repoId
//     * @return Response|string
//     * @throws NotFoundHttpException
//     * @throws ForbiddenHttpException
//     */
//    public function actionIndex(int $repoId): Response|string
//    {
//        $repo = $this->findRepo($repoId);
//        $rootItems = Item::find()
//            ->where([
//                'repoId' => $repo->id,
//                'parentItemId' => null,
//            ])
//            ->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])
//            ->all();
//
//        return $this->render('index', [
//            'repo' => $repo,
//            'rootItems' => $rootItems,
//        ]);
//    }

    /**
     * Displays a single Item model.
     * @param int $repoId
     * @param int $itemId
     * @param int $postId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionView(int $repoId, int $itemId, int $postId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repo->id, $itemId);
        $post = $this->findPost($item->id, $postId);

        return $this->render('view', [
            'post' => $post,
            'item' => $item,
            'repo' => $repo,
        ]);
    }

    /**
     * Creates a new Item model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \DateMalformedStringException
     * @throws \yii\db\Exception
     */
    public function actionCreate(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repo->id, $itemId);
        $postFormService = new PostFormService();
        $post = $postFormService->prepareForCreate($item, $this->getLoggedUser());

        if (Yii::$app->request->isPost) {
            if ($postFormService->save($post, Yii::$app->request->post(), $_FILES)) {
                return $this->redirect(['posts/view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]);
            }
        }

        return $this->render('create', [
            'post' => $post,
            'item' => $item,
            'repo' => $repo,
        ]);
    }

    /**
     * @param int $repoId
     * @param int $itemId
     * @param int $postId
     * @return Response|string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionUpdate(int $repoId, int $itemId, int $postId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repo->id, $itemId);
        $postFormService = new PostFormService();
        $post = $postFormService->prepareForUpdate(
            $this->findPost($item->id, $postId),
            $this->getLoggedUser(),
        );

        if (Yii::$app->request->isPost) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($postFormService->save($post, Yii::$app->request->post(), $_FILES)) {
                return $this->redirect(['view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]);
            }
        }
        return $this->render('update', [
            'post' => $post,
            'item' => $item,
            'repo' => $repo,
        ]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $repoId
     * @param int $itemId
     * @param int $postId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(int $repoId, int $itemId, int $postId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repoId, $itemId);
        $post = $this->findPost($item->id, $postId);

        if (Yii::$app->request->isPost) {
            if (!(new PostDeletionService())->delete($post)) {
                return $this->render('delete', [
                    'item' => $item,
                    'repo' => $repo,
                    'post' => $post,
                ]);
            }
            return $this->redirect(['items/view', 'repoId' => $repo->id, 'itemId' => $item->itemId]);
        } else {
            return $this->render('delete', [
                'item' => $item,
                'repo' => $repo,
                'post' => $post,
            ]);
        }
    }

    /**
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
     * Finds the Item model based on its repoId and itemId.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $repoId
     * @param int $id
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function findItem(int $repoId, int $id): Item
    {
        if (($model = Item::findOne(['repoId' => $repoId, 'itemId' => $id])) !== null) {
            $model->setItemAccessValidator($this->getItemAccessValidator());
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенный предмет {$repoId}#{$id} не существует");
        }
    }

    /**
     * @param int $itemId
     * @param int $postId
     * @return Post the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function findPost(int $itemId, int $postId): Post
    {
        if (($model = Post::findOne(['itemId' => $itemId, 'id' => $postId])) !== null) {
//            $model->setItemAccessValidator($this->getItemAccessValidator());
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенный пост {$postId} не существует");
        }
    }


    private function getItemAccessValidator(): ItemAccessValidator
    {
        return new ItemAccessValidator()->setUser($this->getLoggedUser());
    }

    private function getLoggedUser(): \yii\web\User
    {
        return Yii::$app->getUser();
    }
}
