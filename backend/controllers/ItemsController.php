<?php

declare(strict_types=1);
namespace backend\controllers;

use backend\models\ItemTagsForm;
use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\ItemTag;
use common\models\Repo;
use Yii;
use common\models\Item;
use common\models\ItemPhoto;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * ItemsController implements the CRUD actions for Item model.
 */
class ItemsController extends Controller
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
        $rootItems = Item::find()
            ->where([
                'repoId' => $repo->id,
                'parentItemId' => null,
            ])
            ->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'repo' => $repo,
            'rootItems' => $rootItems,
        ]);
    }

    /**
     * @param int $repoId
     * @param string|null $id
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionPickContainer(int $repoId, ?string $id = null): Response|string
    {
        $repo = $this->findRepo($repoId);
        $query = Item::find()
            ->where(['repoId' => $repo->id])
            ->andWhere('isContainer != 0')
            ->orderBy(['priority' => SORT_DESC, 'id' => SORT_ASC]);
        $parentContainer = $id ? (clone $query)->andWhere('itemId = :containerId', ['containerId' => $id])->one() : null;
        $containers = $id
            ? (clone $query)->andWhere('parentItemId = :containerId', ['containerId' => $id])->all()
            : (clone $query)->andWhere('parentItemId IS NULL')->all();
        $this->layout = 'blank';
        return $this->render('pick-container', [
            'parentContainerItemId' => $id,
            'parentContainer' => $parentContainer,
            'containers' => $containers,
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
        $queryWords = array_filter(preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY), function($value) { return $value !== ''; });
        $containers = [];
        if (count($queryWords) > 0) {
            $query = Item::find()
                ->where(['repoId' => $repo->id])
                ->andWhere('isContainer != 0');
            $i = 0;
            $hasPositiveCondition = false;
            foreach ($queryWords as $queryWord) {
                if ($queryWord[0] !== '-') {
                    $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id");
                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                    $hasPositiveCondition = true;
                } else {
                    $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id AND t{$i}.tag LIKE :tagMask{$i}");
                    $queryWord = mb_substr($queryWord, 1);
                    $query->andWhere("t{$i}.tag IS NULL AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                }
                $i++;
            }
            $query->groupBy(Item::tableName() . '.id');
            if ($hasPositiveCondition) {
                $containers = $query->all();
            }
        }
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

        $container = $containerId !== null ? $this->findModel($repo->id, $containerId) : null;

        $queryWords = $queryString !== null ? array_filter(preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY), function($value) { return $value !== ''; }) : [];

        $items = null;
        $query = Item::find()->where(['repoId' => $repo->id]);
        $hasPositiveCondition = false;
        if (count($queryWords) > 0) {
            $i = 0;
            foreach ($queryWords as $queryWord) {
                if ($queryWord[0] !== '-') {
                    $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id");
                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                    $hasPositiveCondition = true;
                } else {
                    $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id AND t{$i}.tag LIKE :tagMask{$i}");
                    $queryWord = mb_substr($queryWord, 1);
                    $query->andWhere("t{$i}.tag IS NULL AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                }
                $i++;
            }
            $query->groupBy(Item::tableName() . '.id')
                ->orderBy(Item::tableName() . '.isContainer DESC, ' . Item::tableName() . '.id ASC');
        }

        if ($itemId !== null && $itemId !== '') {
            $query->andWhere(Item::tableName() . '.itemId = :itemId', ['itemId' => $itemId]);
            $hasPositiveCondition = true;
        }

        if ($hasPositiveCondition) {
//            var_dump($query->createCommand()->getRawSql());die;
            $items = $query->all();
        }

        // Если найден ровно 1 результат, то сразу перекидываем на страницу этого предмета
        if (is_array($items) && count($items) === 1) {
            return $this->redirect(['/items/view', 'repoId' => $repo->id, 'id' => $items[0]->itemId, 'q' => $queryString]);
        }

        $paths = [];
        $isMoreThan = false;
        if (is_array($items)) {
            $maxResults = 2000;
            $tmpItems = [];
            $i = 0;
            foreach ($items as $item) {
                if ($i >= $maxResults) {
                    $isMoreThan = true;
                    break;
                }
                $doSkipItem = $containerId !== null;
                $path = $this->getItemPathForView($item, $repo);
                if ($containerId) {
                    foreach ($path as $pathItem) {
                        if ($pathItem['itemId'] == $containerId) {
                            $doSkipItem = false;
                            break;
                        }
                    }
                }
                if (!$doSkipItem) {
                    $tmpItems[] = $item;
                    $paths[$item->id] = $path;
                    $i++;
                }
            }
            $items = $tmpItems;
        }

        return $this->render('search', [
            'items' => $items, // null -- если поиск не выполнялся, [] -- если ничего не найдено
            'paths' => $paths,
            'query' => $queryString,
            'itemId' => $itemId,
            'searchInside' => $containerId !== null,
            'containerId' => $containerId,
            'container' => $container,
            'isMoreThan' => $isMoreThan,
            'repo' => $repo,
        ]);
    }

    private function getItemPathForView(Item $item, Repo $repo): array
    {
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

    /**
     * Displays a single Item model.
     * @param int $repoId
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionView(int $repoId, int $id): Response|string
    {
        $repo = $this->findRepo($repoId);
        $model = $this->findModel($repo->id, $id);
        $queryString = Yii::$app->request->getQueryParam('q', '');

        $prevItem = Item::find()->where(['repoId' => $repo->id])->andWhere('itemId < :id', ['id' => $id])->orderBy('id DESC')->limit(1)->one();
        $nextItem = Item::find()->where(['repoId' => $repo->id])->andWhere('itemId > :id', ['id' => $id])->orderBy('id ASC')->limit(1)->one();

        return $this->render('view', [
            'model' => $model,
            'repo' => $repo,
            'children' => $model->getItems()->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all(),
            'containerId' => $id,
            'prevItem' => $prevItem,
            'nextItem' => $nextItem,
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
        $item = new Item();
        $item->scenario = Item::SCENARIO_CREATE;
        $item->setItemAccessValidator($this->getItemAccessValidator());
        $item->repoId = $repo->id;
        $item->priority = 0;
        $item->createdBy = $this->getLoggedUser()->id;

        $tagsForm = new ItemTagsForm();

        if ($parentItemId) {
            $parent = $this->findParentItem($repo->id, $parentItemId);
            $item->parentItemId = $parent->itemId;
        } else {
            $parent = null;
        }
        $item->isContainer = (bool) Yii::$app->request->getQueryParam('isContainer');

        $goto = Yii::$app->request->post('goto', Yii::$app->request->getQueryParam('goto', 'view'));

        if (Yii::$app->request->isPost) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($item->load(Yii::$app->request->post()) && $item->save()) {

                // Обновляем lastItemId в репозитории
                $repo->lastItemId = $item->itemId;
                $repo->save();

                if ($tagsForm->load(Yii::$app->request->post())) {
                    $item->saveTagsFromString($tagsForm->tags);
                }

                $tmpNames = $_FILES['photos']['tmp_name'];

                $sortIndex = 0;
                foreach ($tmpNames as $photoId => $photoValue) {
                    if ($photoValue === '') {
                        continue;
                    }
                    if (array_key_exists($photoId, $tmpNames)) {
                        $photo = new ItemPhoto();
                        $photo->itemId = $item->id;
                        $photo->sortIndex = $sortIndex;
                        $photo->assignFile($_FILES['photos']['tmp_name'][$photoId]);
                        $photo->save();
                        $sortIndex++;
                    }
                }
                return $this->redirect($goto === 'create'
                    ? ['items/create', 'repoId' => $repo->id, 'parentItemId' => $parentItemId, 'goto' => $goto]
                    : ['items/view', 'repoId' => $repo->id, 'id' => $item->itemId]);
            }
        }
        return $this->render('create', [
            'model' => $item,
            'parent' => $parent,
            'repo' => $repo,
            'tagsForm' => $tagsForm,
            'goto' => $goto,
        ]);
    }

    /**
     * Updates an existing Item model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $repoId
     * @param int $id
     * @return Response|string
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\db\Exception
     */
    public function actionUpdate(int $repoId, int $id): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findModel($repoId, $id);
        $item->scenario = Item::SCENARIO_UPDATE;

        $tagsForm = new ItemTagsForm();
        $tagsForm->tags = $item->fetchTagsAsString();

        if (Yii::$app->request->isPost) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($item->load(Yii::$app->request->post()) && $item->save()) {

                if ($tagsForm->load(Yii::$app->request->post())) {
                    $item->saveTagsFromString($tagsForm->tags);
                }

                $tmpNames = $_FILES['photos']['tmp_name'];

                foreach ($tmpNames as $photoId => $photoValue) {
                    if ($photoValue === '') { // Check "upload_max_filesize"
                        continue;
                    }
                    if (array_key_exists($photoId, $tmpNames)) {
                        $photo = new ItemPhoto();
                        $photo->itemId = $item->id;
                        $photo->assignFile($_FILES['photos']['tmp_name'][$photoId]);
                        $photo->save();
                    }
                }
                return $this->redirect(['view', 'repoId' => $repo->id, 'id' => $item->itemId]);
            }
        }
        return $this->render('update', [
            'model' => $item,
            'repo' => $repo,
            'tagsForm' => $tagsForm,
        ]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $repoId
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException if the model cannot be found
     * @throws ForbiddenHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete(int $repoId, int $id): Response|string
    {
        $repo = $this->findRepo($repoId);
        $item = $this->findModel($repoId, $id);

        if (Yii::$app->request->isPost) {
            $parentItemId = $item->parentItemId;

            if ($item->delete() === false) {
                return $this->render('delete', [
                    'model' => $item,
                    'repo' => $repo,
                ]);
            }

            return $this->redirect(
                $parentItemId
                    ? ['items/view', 'repoId' => $repo->id, 'id' => $parentItemId]
                    : ['items/index', 'repoId' => $repo->id]
            );


        } else {
            return $this->render('delete', [
                'model' => $item,
                'repo' => $repo,
            ]);
        }
    }

    /**
     * Импорт предметов в контейнер
     *
     * @param int $repoId
     * @param int $parentItemId
     * @return Response|string
     * @throws Exception
     * @throws HttpException
     * @todo Завернуть в транзакцию, чтобы исключить частичный импорт
     */
    public function actionImport(int $repoId, int $parentItemId): Response|string
    {
        $repo = $this->findRepo($repoId);
        $parentItem = $this->findParentItem($repo->id, $parentItemId);
        $text = Yii::$app->request->post('text');
        $confirm = (bool) Yii::$app->request->post('confirm');
        $items = [];

        $errorLine = null;
        $errorStr = null;
        $errorMsg = null;

        $line = 1;
        $item = [];

        $addProperty = function(string $key, string $value) use (&$item) {
            if (!in_array($key, ['description', 'tags', 'container'], true)) {
                throw new Exception('Unknown property "' . $key . '"');
            }
            if ($key === 'container') {
                $value = $value ? '1' : '0';
            }
            if (array_key_exists($key, $item)) {
                switch ($key) {
                    case 'description' :
                        $item[$key] .= "\n" . $value;
                        break;
                    case 'tags' :
                        $item[$key] .= ', ' . $value;
                        break;
                    default :
                        $item[$key] = $value;
                }
            } else {
                $item[$key] = $value;
            }
        };

        $str = '';
        try {
            foreach (explode("\n", $text) as $str) {
                $str = trim($str);

                if ($str === '') {
                    continue;
                }

                switch ($str[0]) {
                    case '*' :
                        if (preg_match('/^\*\s*(\w+)\s*:\s*(.*)$/ui', $str, $m)) {
                            $key = mb_strtolower(trim($m[1]));
                            $replacements = [
                                'метки' => 'tags',
                                'теги' => 'tags',
                                'тэги' => 'tags',
                                'desc' => 'description',
                                'описание' => 'description',
                                'cont' => 'container',
                                'контейнер' => 'container',
                                'конт' => 'container',
                            ];
                            foreach ($replacements as $from => $to) {
                                if ($key === $from) {
                                    $key = $to;
                                    break;
                                }
                            }
                            $value = trim($m[2]);
                            $addProperty($key, $value);
                        } else {
                            throw new Exception('Invalid property line format');
                        }
                        break;
                    case '!' :
                        $key = 'description';
                        $value = trim(mb_substr($str, 1));
                        $addProperty($key, $value);
                        break;

                    case '#' :
                        $key = 'tags';
                        $value = trim(mb_substr($str, 1));
                        $addProperty($key, $value);
                        break;

                    default :
                        if (isset($item['name'])) {
                            $items[] = $item;
                            $item = [];
                        }
                        $item['name'] = $str;
                }
                $line++;
            }
            if (isset($item['name'])) {
                $items[] = $item;
            }
        } catch (Exception $e) {
            $errorLine = $line;
            $errorStr = $str;
            $errorMsg = $e->getMessage();
        }

        $firstItemAnchor = null;

        if ($confirm && $errorLine === null) {
            foreach ($items as $item) {
                $itemModel = new Item();
                $itemModel->scenario = Item::SCENARIO_CREATE;
                $itemModel->setItemAccessValidator($this->getItemAccessValidator());
                $itemModel->repoId = $repo->id;
                $itemModel->name = $item['name'];
                $itemModel->parentItemId = $parentItem->itemId;
                $itemModel->isContainer = !empty($item['container']) ? '1' : '0';
                $itemModel->description = $item['description'] ?? '';
                $itemModel->createdBy = $this->getLoggedUser()->id;
                if (!$itemModel->save()) {
                    throw new Exception(ValidateErrorsFormatter::getMessage($itemModel));
                }

                // Обновляем lastItemId в репозитории
                $repo->lastItemId = $itemModel->itemId;
                $repo->save();

                if (isset($item['tags'])) {
                    $itemModel->saveTagsFromString($item['tags']);
                }

                if ($firstItemAnchor === null) {
                    $firstItemAnchor = 'item' . $itemModel->repoId . '-' . $itemModel->itemId;
                }
            }
            return $this->redirect(Url::to(['view', 'repoId' => $repo->id, 'id' => $parentItem->itemId]) . '#' . $firstItemAnchor);
        }

        return $this->render('import', [
            'text' => $text,
            'parent' => $parentItem,
            'repo' => $repo,
            'items' => $items,
            'errorLine' => $errorLine,
            'errorStr' => $errorStr,
            'errorMsg' => $errorMsg,
        ]);
    }

    /**
     * @param int $repoId
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionJsonPreview(int $repoId, int $id): Response
    {
        $repo = $this->findRepo($repoId);
        $model = $this->findModel($repo->id, $id);
        return $this->asJson([
            'content' => $this->renderPartial('_items', [
                'items' => [$model],
                'paths' => [
                    $model->id => $this->getItemPathForView($model, $repo),
                ],
                'showPath' => true,
                'showChildren' => false,
                'containerId' => null,
            ]),
        ]);
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
    private function findModel(int $repoId, int $id): Item
    {
        if (($model = Item::findOne(['repoId' => $repoId, 'itemId' => $id])) !== null) {
            $model->setItemAccessValidator($this->getItemAccessValidator());
            return $model;
        } else {
            throw new NotFoundHttpException("Запрошенный предмет {$repoId}#{$id} не существует");
        }
    }

    /**
     * Finds the Item model based on its repoId and parentItemId.
     * @param int $repoId
     * @param int $parentItemId
     * @return Item
     * @throws NotFoundHttpException
     */
    private function findParentItem(int $repoId, int $parentItemId): Item
    {
        if (($model = Item::findOne(['repoId' => $repoId, 'itemId' => $parentItemId])) !== null) {
            $model->setItemAccessValidator($this->getItemAccessValidator());
            return $model;
        } else {
            throw new NotFoundHttpException("Родительский контейнер {$repoId}#{$parentItemId} не существует");
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
