<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\services\PostDeletionService;
use backend\services\PostFormService;
use common\models\Post;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PostsController implements the CRUD actions for Post model.
 */
class PostsController extends RepoAwareController
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
     * Displays a single Post model.
     *
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
     * Creates a new Post model.
     *
     * If creation is successful, the browser will be redirected to the 'view' page.
     *
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
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
     * Deletes an existing Post model.
     *
     * If deletion is successful, the browser will be redirected to the item page.
     *
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
     * @param int $itemId
     * @param int $postId
     * @return Post the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function findPost(int $itemId, int $postId): Post
    {
        if (($model = Post::findOne(['itemId' => $itemId, 'id' => $postId])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенный пост {$postId} не существует");
        }
    }
}
