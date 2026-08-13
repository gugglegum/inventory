<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\services\PostDeletionService;
use backend\services\ItemViewDataService;
use backend\services\PhotoEditorService;
use backend\services\PostFormService;
use common\helpers\PostDataHelper;
use common\models\Post;
use Yii;
use yii\base\Exception;
use yii\data\ActiveDataProvider;
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
    private const int POST_PAGE_SIZE = 20;

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
                    'quick-create' => ['post'],
                    'create' => ['get', 'post'],
                    'update' => ['get', 'post'],
                    'delete' => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * Показывает полный журнал заметок предмета с пагинацией.
     */
    public function actionIndex(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repo->id, $itemId);
        $dataProvider = new ActiveDataProvider([
            'query' => Post::find()
                ->where(['itemId' => $item->id])
                ->with(['postPhotos.photo'])
                ->orderBy([
                    'datetime' => SORT_DESC,
                    'id' => SORT_DESC,
                ]),
            'pagination' => [
                'pageSize' => self::POST_PAGE_SIZE,
                'pageSizeLimit' => [self::POST_PAGE_SIZE, self::POST_PAGE_SIZE],
            ],
            'sort' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'item' => $item,
            'repo' => $repo,
        ]);
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

        if (Yii::$app->request->isAjax && Yii::$app->request->getQueryParam('modal') === '1') {
            return $this->renderPartial('_modalContent', [
                'post' => $post,
                'item' => $item,
                'repo' => $repo,
            ]);
        }

        return $this->render('view', [
            'post' => $post,
            'item' => $item,
            'repo' => $repo,
        ]);
    }

    /**
     * Создает короткую заметку с текущей датой без перехода на полную форму.
     *
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionQuickCreate(int $repoId, int $itemId): Response
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repo->id, $itemId);
        $postFormService = new PostFormService();
        $postForm = $postFormService->prepareForCreate($item, $this->getLoggedUser());

        if ($postFormService->save($postForm, PostDataHelper::toArray(Yii::$app->request->post()))) {
            return $this->redirectToItemPost($repo->id, (int) $item->itemId, $postForm->getPost());
        }

        Yii::$app->session->setFlash('error', implode(' ', $postForm->getFirstErrors()));
        return $this->redirect(['items/view', 'repoId' => $repo->id, 'itemId' => $item->itemId]);
    }

    /**
     * Creates a new Post model.
     *
     * If creation is successful, the browser will be redirected to the post card on the item page.
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
        $postForm = $postFormService->prepareForCreate($item, $this->getLoggedUser());
        $photoEditorService = new PhotoEditorService();
        $photoEditorForm = $photoEditorService->createFormForPost($postForm->getPost());

        if (Yii::$app->request->isPost) {
            $postData = PostDataHelper::toArray(Yii::$app->request->post());
            $photoEditorForm->load($postData);
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $photoPlan = $photoEditorService->prepareForPost(
                    $photoEditorForm,
                    $postForm->getPost(),
                    $repo->id,
                    (int) $this->getLoggedUser()->id,
                );
                if ($photoPlan !== null && $postFormService->save($postForm, $postData)) {
                    $post = $postForm->getPost();
                    $detachedPhotoIds = $photoEditorService->applyToPost($photoPlan, $post);
                    $transaction->commit();
                    $photoEditorService->cleanupDetachedPhotos($detachedPhotoIds);

                    return $this->redirectToItemPost($repo->id, (int) $item->itemId, $post);
                }

                if ($photoPlan === null) {
                    $postForm->load($postData);
                    $postForm->validate();
                }
                $transaction->rollBack();
            } catch (\Throwable $exception) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                throw $exception;
            }
        }

        $photoEntries = $photoEditorService->viewEntriesForPost(
            $photoEditorForm,
            $postForm->getPost(),
            $repo->id,
            (int) $this->getLoggedUser()->id,
        );

        return $this->render('create', [
            'postForm' => $postForm,
            'item' => $item,
            'repo' => $repo,
            'photoEditorForm' => $photoEditorForm,
            'photoEntries' => $photoEntries,
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
        $postForm = $postFormService->prepareForUpdate(
            $this->findPost($item->id, $postId),
            $this->getLoggedUser(),
        );
        $photoEditorService = new PhotoEditorService();
        $photoEditorForm = $photoEditorService->createFormForPost($postForm->getPost());

        if (Yii::$app->request->isPost) {
            $postData = PostDataHelper::toArray(Yii::$app->request->post());
            $photoEditorForm->load($postData);
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $photoPlan = $photoEditorService->prepareForPost(
                    $photoEditorForm,
                    $postForm->getPost(),
                    $repo->id,
                    (int) $this->getLoggedUser()->id,
                );
                if ($photoPlan !== null && $postFormService->save($postForm, $postData)) {
                    $post = $postForm->getPost();
                    $detachedPhotoIds = $photoEditorService->applyToPost($photoPlan, $post);
                    $transaction->commit();
                    $photoEditorService->cleanupDetachedPhotos($detachedPhotoIds);

                    return $this->redirectToItemPost($repo->id, (int) $item->itemId, $post);
                }

                if ($photoPlan === null) {
                    $postForm->load($postData);
                    $postForm->validate();
                }
                $transaction->rollBack();
            } catch (\Throwable $exception) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                throw $exception;
            }
        }
        $photoEntries = $photoEditorService->viewEntriesForPost(
            $photoEditorForm,
            $postForm->getPost(),
            $repo->id,
            (int) $this->getLoggedUser()->id,
        );
        return $this->render('update', [
            'postForm' => $postForm,
            'item' => $item,
            'repo' => $repo,
            'photoEditorForm' => $photoEditorForm,
            'photoEntries' => $photoEntries,
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

    /**
     * Возвращает пользователя к заметке на странице предмета или в полном журнале.
     */
    private function redirectToItemPost(int $repoId, int $itemId, Post $post): Response
    {
        $newerPostCount = (int) Post::find()
            ->where(['itemId' => $post->itemId])
            ->andWhere([
                'or',
                ['>', 'datetime', (int) $post->datetime],
                [
                    'and',
                    ['datetime' => (int) $post->datetime],
                    ['>', 'id', $post->id],
                ],
            ])
            ->count();

        if ($newerPostCount >= ItemViewDataService::RECENT_POST_LIMIT) {
            $route = [
                'posts/index',
                'repoId' => $repoId,
                'itemId' => $itemId,
                '#' => 'post-' . $post->id,
            ];
            $page = intdiv($newerPostCount, self::POST_PAGE_SIZE) + 1;
            if ($page > 1) {
                $route['page'] = $page;
            }

            return $this->redirect($route);
        }

        return $this->redirect([
            'items/view',
            'repoId' => $repoId,
            'itemId' => $itemId,
            '#' => 'post-' . $post->id,
        ]);
    }
}
