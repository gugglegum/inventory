<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\InventoryItemConfirmForm;
use backend\models\InventoryItemUnconfirmForm;
use common\components\ItemAccessValidator;
use common\models\Inventory;
use common\models\Repo;
use Yii;
use common\models\Item;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * InventoryController
 */
class InventoryController extends Controller
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

        /** @var Item[] $confirmedItems */
        $confirmedItems = $repo->getItems()->innerJoinWith('inventoryItems')->where(['inventory_item.inventoryId' => $inventory->id])->orderBy(['inventory_item.created' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all();
        /** @var Item[] $notConfirmedItems */
        $notConfirmedItems = $container->getItems()->andWhere(['NOT IN', 'id', ArrayHelper::getColumn($confirmedItems, 'id')])->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all();

        $paths = [];
        foreach ($confirmedItems as $item) {
            $paths[$item->id] = $this->getItemPathForView($item, $repo, $container);
        }
        foreach ($notConfirmedItems as $item) {
            $paths[$item->id] = $this->getItemPathForView($item, $repo, $container);
        }

        $inventoryItemConfirm = new InventoryItemConfirmForm();
        $inventoryItemUnconfirm = new InventoryItemUnconfirmForm();
        if (Yii::$app->request->isPost) {
            if ($inventoryItemConfirm->load(Yii::$app->request->post())) {
                $inventoryItemConfirm->inventoryId = $inventory->id;
                $inventoryItemConfirm->createdBy = $this->getLoggedUser()->id;
                if ($inventoryItemConfirm->save()) {
                    return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
                }
            }
            elseif ($inventoryItemUnconfirm->load(Yii::$app->request->post())) {
                $inventoryItemUnconfirm->inventoryId = $inventory->id;
                $inventoryItemUnconfirm->createdBy = $this->getLoggedUser()->id;
                if ($inventoryItemUnconfirm->save()) {
                    return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
                }
            }
        }

        return $this->render('view', [
            'inventory' => $inventory,
            'container' => $container,
            'notConfirmedItems' => $notConfirmedItems,
            'confirmedItems' => $confirmedItems,
            'paths' => $paths,
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
        $inventory = new Inventory();
//        $inventory->scenario = Post::SCENARIO_CREATE;
//        $post->setItemAccessValidator($this->getItemAccessValidator());
        $inventory->containerId = $container->id;
        $inventory->status = Inventory::STATUS_OPENED;
        $inventory->createdBy = $this->getLoggedUser()->id;

        if (Yii::$app->request->isPost) {
            if (!$inventory->save()) {
                if ($inventory->hasErrors()) {
                    $firstErrors = $inventory->getFirstErrors();
                    throw new Exception(reset($firstErrors));
                }
            }
        }
        return $this->redirect(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]);
    }

    /**
     * @param int $repoId
     * @param int $itemId
     * @param int $inventoryId
     * @return Response|string
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionClose(int $repoId, int $itemId, int $inventoryId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $container = $this->findItem($repo->id, $itemId);
        $inventory = $this->findInventory($container->id, $inventoryId);

        // Подтверждённые предметы
        foreach ($inventory->inventoryItems as $inventoryItem) {
            $item = $inventoryItem->item;
            $item->setItemAccessValidator($this->getItemAccessValidator());
            $item->scenario = Item::SCENARIO_UPDATE;
            $item->lastSeen = $inventoryItem->created;
            $item->lastSeenBy = $inventoryItem->createdBy;
            $item->missingSince = null;
            $item->parentItemId = $container->itemId;
            $item->save();
        }
        /** @var Item[] $inventoryItems */
        $inventoryItems = $container->getItems()->innerJoinWith('inventoryItems')->where(['inventory_item.inventoryId' => $inventory->id])->all();
        /** @var Item[] $containerItems */
        $containerItems = $container->getItems()->andWhere(['NOT IN', 'id', ArrayHelper::getColumn($inventoryItems, 'id')])->all();
        $now = time();
        foreach ($containerItems as $item) {
            $item->setItemAccessValidator($this->getItemAccessValidator());
            $item->scenario = Item::SCENARIO_UPDATE;
            $item->missingSince = $now;
            $item->missingSinceBy = $this->getLoggedUser()->id;
            $item->save();
        }
        $inventory->status = Inventory::STATUS_CLOSED;
        $inventory->closed = $now;
        $inventory->closedBy = $this->getLoggedUser()->id;
        $inventory->save();
        return $this->redirect(['items/view', 'repoId' => $repo->id, 'id' => $container->itemId]);
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

        if (Yii::$app->request->isPost) {
            if ($inventory->delete() === false) {
                throw new Exception('Failed to delete inventory');
            }
        }
        return $this->redirect(['inventory/index', 'repoId' => $repo->id, 'itemId' => $container->itemId]);
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
     * @param int $inventoryId
     * @return Inventory the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function findInventory(int $itemId, int $inventoryId): Inventory
    {
        if (($model = Inventory::findOne(['containerId' => $itemId, 'id' => $inventoryId])) !== null) {
//            $model->setItemAccessValidator($this->getItemAccessValidator());
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенная инвентаризация {$inventoryId} не существует");
        }
    }

    private function getItemPathForView(Item $item, Repo $repo, Item $container): array
    {
        if ($item->parentItemId === $container->itemId) {
            return [];
        }
        $path = [];
        $tmpItem = $item;
        while ($tmpItem) {
            $path[] = [
                'itemId' => $tmpItem->itemId,
                'repoId' => $tmpItem->repoId,
                'label' => $tmpItem->name,
                'url' => ['items/view', 'repoId' => $repo->id, 'id' => $tmpItem->itemId],
            ];
            $tmpItem = $tmpItem->parentItem;
        }
        return $path;
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
