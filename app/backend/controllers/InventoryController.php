<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\InventoryItemConfirmForm;
use backend\models\InventoryItemUnconfirmForm;
use backend\services\InventoryCloseService;
use backend\services\InventoryItemConfirmationService;
use backend\services\InventoryLifecycleService;
use backend\services\InventoryViewDataService;
use common\helpers\PostDataHelper;
use common\models\Inventory;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер инвентаризаций контейнеров.
 *
 * Управляет списком инвентаризаций, просмотром текущей проверки, подтверждением найденных предметов
 * и HTTP-обвязкой закрытия/удаления инвентаризации.
 */
class InventoryController extends RepoAwareController
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
                    'view' => ['get', 'post'],
                    'create' => ['post'],
                    'close' => ['post'],
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @param int $repoId
     * @param int $itemId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionIndex(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repoId, $itemId);
        $inventories = $container->getInventories()->orderBy(['id' => SORT_DESC])->all();
        if (!$container->isContainer && count($inventories) === 0) {
            throw new ForbiddenHttpException('Инвентаризации бывают только у предметов-контейнеров');
        }
        return $this->render('index', [
            'repo' => $repo,
            'container' => $container,
            'inventories' => $inventories,
        ]);
    }

    /**
     * Displays a single Item model.
     * @param int $repoId
     * @param int $itemId
     * @param int $inventoryId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     * @throws \Throwable
     */
    public function actionView(int $repoId, int $itemId, int $inventoryId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repo->id, $itemId);
        $inventory = $this->findInventory($container->id, $inventoryId);

        $inventoryItemConfirm = new InventoryItemConfirmForm();
        $inventoryItemUnconfirm = new InventoryItemUnconfirmForm();
        $inventoryItemConfirmationService = new InventoryItemConfirmationService();
        if (Yii::$app->request->isPost) {
            $postData = PostDataHelper::toArray(Yii::$app->request->post());
            if ($inventoryItemConfirm->load($postData)) {
                $inventoryItemConfirm->repoId = $repo->id;
                if ($inventoryItemConfirm->validate() && $inventoryItemConfirm->itemId !== null) {
                    $item = $this->findItem($repo->id, $inventoryItemConfirm->itemId);
                    $confirmResult = $inventoryItemConfirmationService->confirm($inventory, $item, $this->getLoggedUser());
                    if (!$confirmResult->hasError()) {
                        return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
                    }
                    $inventoryItemConfirm->addError('itemId', $confirmResult->errorMessage ?? 'Unknown error');
                }
            } elseif ($inventoryItemUnconfirm->load($postData)) {
                $inventoryItemUnconfirm->repoId = $repo->id;
                if ($inventoryItemUnconfirm->validate() && $inventoryItemUnconfirm->itemId !== null) {
                    $item = $this->findItem($repo->id, $inventoryItemUnconfirm->itemId);
                    if ($inventoryItemConfirmationService->unconfirm($inventory, $item)) {
                        return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
                    }
                    $inventoryItemUnconfirm->addError('itemId', 'Предмет не был подтвержден в этой инвентаризации.');
                }
            }
        }

        $inventoryViewData = (new InventoryViewDataService())->prepare($repo, $container, $inventory);

        return $this->render('view', [
            'inventory' => $inventory,
            'container' => $container,
            'notConfirmedItems' => $inventoryViewData->notConfirmedItems,
            'confirmedItems' => $inventoryViewData->confirmedItems,
            'paths' => $inventoryViewData->paths,
            'repo' => $repo,
            'inventoryItemConfirm' => $inventoryItemConfirm,
            'inventoryItemUnconfirm' => $inventoryItemUnconfirm,
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
     * @throws \yii\db\Exception
     */
    public function actionCreate(int $repoId, int $itemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repo->id, $itemId);

        $inventory = (new InventoryLifecycleService())->open($container, $this->getLoggedUser());

        return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
    }

    /**
     * Закрывает инвентаризацию и передает бизнес-изменения в сервисный слой.
     *
     * После успешного закрытия подтвержденные и отсутствующие предметы обновляются через InventoryCloseService,
     * а пользователь возвращается на страницу контейнера.
     *
     * @param int $repoId
     * @param int $itemId
     * @param int $inventoryId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     * @throws \Throwable
     */
    public function actionClose(int $repoId, int $itemId, int $inventoryId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repo->id, $itemId);
        $inventory = $this->findInventory($container->id, $inventoryId);

        (new InventoryCloseService())->close(
            $inventory,
            $container,
            $this->getLoggedUser(),
            $this->getItemAccessValidator(),
        );

        return $this->redirect(['items/view', 'repoId' => $repo->id, 'itemId' => $container->itemId]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $repoId
     * @param int $itemId
     * @param int $inventoryId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(int $repoId, int $itemId, int $inventoryId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repo->id, $itemId);
        $inventory = $this->findInventory($container->id, $inventoryId);

        (new InventoryLifecycleService())->delete($inventory);

        return $this->redirect(['inventory/index', 'repoId' => $repo->id, 'itemId' => $container->itemId]);
    }

    /**
     * @param int $itemId
     * @param int $inventoryId
     * @return Inventory the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function findInventory(int $itemId, int $inventoryId): Inventory
    {
        if (($model = Inventory::findOne(['containerId' => $itemId, 'id' => $inventoryId])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенная инвентаризация {$inventoryId} не существует");
        }
    }
}
