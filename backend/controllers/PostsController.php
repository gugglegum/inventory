<?php

declare(strict_types=1);
namespace backend\controllers;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\Repo;
use Yii;
use common\models\Item;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
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
        $post = new Post();
        $post->scenario = Post::SCENARIO_CREATE;
//        $post->setItemAccessValidator($this->getItemAccessValidator());
        $post->itemId = $item->id;
        $post->createdBy = $this->getLoggedUser()->id;

        if (Yii::$app->request->isPost) {
            if ($post->load(Yii::$app->request->post()) && $post->save()) {

                $tmpNames = $_FILES['photos']['tmp_name'];

                $sortIndex = 0;
                foreach ($tmpNames as $photoId => $photoValue) {
                    if ($photoValue === '') {
                        continue;
                    }
                    if (array_key_exists($photoId, $tmpNames)) {
                        $photo = new Photo();
                        $photo->assignFile($_FILES['photos']['tmp_name'][$photoId]);
                        $photo->save();
                        $postPhoto = new PostPhoto();
                        $postPhoto->postId = $post->id;
                        $postPhoto->photoId = $photo->id;
                        $postPhoto->sortIndex = $sortIndex;
                        $postPhoto->save();
                        $sortIndex++;
                    }
                }
                return $this->redirect(['posts/view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]);
            }
        }

        $post->datetimeText = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->format('d.m.Y H:i');
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
        $post = $this->findPost($item->id, $postId);
        $post->scenario = Post::SCENARIO_UPDATE;
        $post->updatedBy = $this->getLoggedUser()->id;

        if (Yii::$app->request->isPost) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($post->load(Yii::$app->request->post()) && $post->save()) {

                $tmpNames = $_FILES['photos']['tmp_name'];

                foreach ($tmpNames as $photoId => $photoValue) {
                    if ($photoValue === '') { // Check "upload_max_filesize"
                        continue;
                    }
                    if (array_key_exists($photoId, $tmpNames)) {
                        $photo = new Photo();
                        $photo->assignFile($_FILES['photos']['tmp_name'][$photoId]);
                        $photo->save();
                        $postPhoto = new PostPhoto();
                        $postPhoto->postId = $post->id;
                        $postPhoto->photoId = $photo->id;
                        $postPhoto->save();
                    }
                }
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
            if ($post->delete() === false) {
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
