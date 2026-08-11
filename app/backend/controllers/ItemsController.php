<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ItemDeleteForm;
use backend\services\ItemDeletionService;
use backend\services\ItemFormAssetService;
use backend\services\ItemFormService;
use backend\services\ItemImportService;
use backend\services\ItemListService;
use backend\services\ItemSearchService;
use backend\services\ItemViewDataService;
use backend\services\PhotoEditorService;
use common\helpers\PostDataHelper;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * ItemsController implements the CRUD actions for Item model.
 */
class ItemsController extends RepoAwareController
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
                    'pick-container' => ['get'],
                    'search-container' => ['get'],
                    'search' => ['get'],
                    'view' => ['get'],
                    'create' => ['get', 'post'],
                    'update' => ['get', 'post'],
                    'delete' => ['get', 'post'],
                    'import' => ['post'],
                    'json-preview' => ['get'],
                ],
            ],
        ];
    }

    /**
     * Lists all Item models.
     * @param int $repoId
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIndex(int $repoId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $rootItems = (new ItemListService())->findRootItems($repo);

        return $this->render('index', [
            'repo' => $repo,
            'rootItems' => $rootItems,
        ]);
    }

    /**
     * @param int $repoId
     * @param string|null $itemId
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionPickContainer(int $repoId, ?string $itemId = null): Response|string
    {
        $repo = $this->findRepo($repoId);
        $pickerData = (new ItemListService())->prepareContainerPicker($repo, $itemId);
        $this->layout = 'blank';
        return $this->render('pick-container', [
            'parentContainerItemId' => $pickerData->parentContainerItemId,
            'parentContainer' => $pickerData->parentContainer,
            'containers' => $pickerData->containers,
            'repo' => $repo,
        ]);
    }

    /**
     * @param int $repoId
     * @param string $q
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionSearchContainer(int $repoId, string $q): Response|string
    {
        $repo = $this->findRepo($repoId);
        $queryString = Yii::$app->request->getQueryParam('q', '');
        $containers = (new ItemListService())->searchContainers($repo, $queryString);
        $this->layout = 'blank';
        return $this->render('search-container', [
            'containers' => $containers,
            'query' => $queryString,
            'repo' => $repo,
        ]);
    }

    /**
     * @param int $repoId
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionSearch(int $repoId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $queryString = Yii::$app->request->getQueryParam('q');
        $containerId = Yii::$app->request->getQueryParam('c') !== null ? (int) Yii::$app->request->getQueryParam('c') : null;
        $itemId = Yii::$app->request->getQueryParam('id');

        $container = $containerId !== null ? $this->findItem($repo->id, $containerId) : null;
        $searchResult = (new ItemSearchService())->search($repo, $queryString, $container, $itemId);
        $items = $searchResult->items;

        // Если найден ровно 1 результат, то сразу перекидываем на страницу этого предмета
        if (is_array($items) && count($items) === 1) {
            return $this->redirect(['/items/view', 'repoId' => $repo->id, 'itemId' => $items[0]->itemId, 'q' => $queryString]);
        }

        return $this->render('search', [
            'items' => $items, // null -- если поиск не выполнялся, [] -- если ничего не найдено
            'paths' => $searchResult->paths,
            'query' => $queryString,
            'itemId' => $itemId,
            'searchInside' => $containerId !== null,
            'containerId' => $containerId,
            'container' => $searchResult->container,
            'isMoreThan' => $searchResult->isMoreThan,
            'repo' => $repo,
        ]);
    }

    /**
     * Displays a single Item model.
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionView(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $model = $this->findItem($repo->id, $itemId);
        $queryString = Yii::$app->request->getQueryParam('q', '');
        $viewData = (new ItemViewDataService())->prepare($model);

        return $this->render('view', [
            'model' => $model,
            'repo' => $repo,
            'children' => $viewData->children,
            'containerId' => $itemId,
            'prevItem' => $viewData->prevItem,
            'nextItem' => $viewData->nextItem,
            'query' => $queryString,
        ]);
    }

    /**
     * Creates a new Item model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @param int $repoId
     * @param int|null $parentItemId
     * @return Response|string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionCreate(int $repoId, ?int $parentItemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $itemFormService = new ItemFormService();
        $parent = $parentItemId !== null ? $this->findParentItem($repo->id, $parentItemId) : null;
        $itemForm = $itemFormService->prepareForCreate(
            $repo,
            $parent,
            $this->getLoggedUser(),
            $this->getItemAccessValidator(),
            (bool) Yii::$app->request->getQueryParam('isContainer'),
        );
        $item = $itemForm->getItem();
        $tagsForm = $itemFormService->createTagsForm();
        $photoEditorService = new PhotoEditorService();
        $photoEditorForm = $photoEditorService->createFormForItem($item);

        $goto = Yii::$app->request->post('goto', Yii::$app->request->getQueryParam('goto', 'view'));

        if (Yii::$app->request->isPost) {
            $postData = PostDataHelper::toArray(Yii::$app->request->post());
            $photoEditorForm->load($postData);
            $tagsForm->load($postData);
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $photoPlan = $photoEditorService->prepareForItem(
                    $photoEditorForm,
                    $item,
                    (int) $this->getLoggedUser()->id,
                );
                if ($photoPlan !== null && $itemFormService->save($itemForm, $postData)) {
                    (new ItemFormAssetService())->save($item, $tagsForm, $postData);
                    $detachedPhotoIds = $photoEditorService->applyToItem($photoPlan, $item);
                    $transaction->commit();
                    $photoEditorService->cleanupDetachedPhotos($detachedPhotoIds);

                    return $this->redirect($goto === 'create'
                        ? ['items/create', 'repoId' => $repo->id, 'parentItemId' => $parentItemId, 'goto' => $goto]
                        : ['items/view', 'repoId' => $repo->id, 'itemId' => $item->itemId]);
                }

                if ($photoPlan === null) {
                    $itemForm->load($postData);
                    $itemForm->validate();
                }
                $transaction->rollBack();
            } catch (\Throwable $exception) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                throw $exception;
            }
        }
        $photoEntries = $photoEditorService->viewEntriesForItem(
            $photoEditorForm,
            $item,
            (int) $this->getLoggedUser()->id,
        );
        return $this->render('create', [
            'model' => $itemForm,
            'parent' => $parent,
            'repo' => $repo,
            'tagsForm' => $tagsForm,
            'goto' => $goto,
            'photoEditorForm' => $photoEditorForm,
            'photoEntries' => $photoEntries,
        ]);
    }

    /**
     * Updates an existing Item model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\db\Exception
     */
    public function actionUpdate(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $itemFormService = new ItemFormService();
        $itemForm = $itemFormService->prepareForUpdate(
            $this->findItem($repoId, $itemId),
            $this->getLoggedUser(),
            $this->getItemAccessValidator(),
        );
        $item = $itemForm->getItem();
        $tagsForm = $itemFormService->createTagsForm($item);
        $photoEditorService = new PhotoEditorService();
        $photoEditorForm = $photoEditorService->createFormForItem($item);

        if (Yii::$app->request->isPost) {
            $postData = PostDataHelper::toArray(Yii::$app->request->post());
            $photoEditorForm->load($postData);
            $tagsForm->load($postData);
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $photoPlan = $photoEditorService->prepareForItem(
                    $photoEditorForm,
                    $item,
                    (int) $this->getLoggedUser()->id,
                );
                if ($photoPlan !== null && $itemFormService->save($itemForm, $postData)) {
                    (new ItemFormAssetService())->save($item, $tagsForm, $postData);
                    $detachedPhotoIds = $photoEditorService->applyToItem($photoPlan, $item);
                    $transaction->commit();
                    $photoEditorService->cleanupDetachedPhotos($detachedPhotoIds);

                    return $this->redirect(['view', 'repoId' => $repo->id, 'itemId' => $item->itemId]);
                }

                if ($photoPlan === null) {
                    $itemForm->load($postData);
                    $itemForm->validate();
                }
                $transaction->rollBack();
            } catch (\Throwable $exception) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                throw $exception;
            }
        }
        $photoEntries = $photoEditorService->viewEntriesForItem(
            $photoEditorForm,
            $item,
            (int) $this->getLoggedUser()->id,
        );
        return $this->render('update', [
            'model' => $itemForm,
            'repo' => $repo,
            'tagsForm' => $tagsForm,
            'photoEditorForm' => $photoEditorForm,
            'photoEntries' => $photoEntries,
        ]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws NotFoundHttpException if the model cannot be found
     * @throws ForbiddenHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findItem($repoId, $itemId);

        $itemDeleteForm = new ItemDeleteForm();
        if (Yii::$app->request->isPost) {
            $parentItemId = $item->parentItemId;
            if ($itemDeleteForm->load(PostDataHelper::toArray(Yii::$app->request->post())) && $itemDeleteForm->validate()) {
                $deletionResult = (new ItemDeletionService())->delete(
                    $item,
                    $itemDeleteForm->isHardDelete(),
                    $this->getLoggedUser(),
                );

                if ($deletionResult->hasError()) {
                    $itemDeleteForm->addError('', $deletionResult->errorMessage ?? 'Unknown error');
                } else {
                    return $this->redirect(
                        $parentItemId !== null
                            ? ['items/view', 'repoId' => $repo->id, 'itemId' => $parentItemId]
                            : ['items/index', 'repoId' => $repo->id]
                    );
                }
            }
        }
        return $this->render('delete', [
            'itemDeleteForm' => $itemDeleteForm,
            'model' => $item,
            'repo' => $repo,
        ]);
    }

    /**
     * Импорт предметов в контейнер
     *
     * @param int $repoId
     * @param int $parentItemId
     * @return Response|string
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function actionImport(int $repoId, int $parentItemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $parentItem = $this->findParentItem($repo->id, $parentItemId);
        $text = Yii::$app->request->post('text');
        $confirm = (bool) Yii::$app->request->post('confirm');
        $importResult = (new ItemImportService())->import(
            $repo,
            $parentItem,
            $text,
            $confirm,
            $this->getLoggedUser(),
            $this->getItemAccessValidator(),
        );

        if ($confirm && !$importResult->hasError()) {
            return $this->redirect(Url::to(['view', 'repoId' => $repo->id, 'itemId' => $parentItem->itemId]) . '#' . $importResult->firstItemAnchor);
        }

        return $this->render('import', [
            'text' => $text,
            'parent' => $parentItem,
            'repo' => $repo,
            'items' => $importResult->items,
            'errorLine' => $importResult->errorLine,
            'errorStr' => $importResult->errorStr,
            'errorMsg' => $importResult->errorMsg,
        ]);
    }

    /**
     * @param int $repoId
     * @param int $itemId
     * @return Response
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionJsonPreview(int $repoId, int $itemId): Response
    {
        $repo = $this->findRepo($repoId);
        $model = $this->findItem($repo->id, $itemId);
        $previewData = (new ItemViewDataService())->preparePreview($model);

        return $this->asJson([
            'content' => $this->renderPartial('_items', [
                'items' => [$model],
                'repo' => $repo,
                'paths' => $previewData->paths,
                'showPath' => true,
                'showChildren' => false,
                'containerId' => null,
            ]),
        ]);
    }
}
