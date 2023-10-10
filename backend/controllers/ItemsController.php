<?php

declare(strict_types=1);
namespace backend\controllers;

use backend\models\ItemTagsForm;
use common\helpers\ValidateErrorsFormatter;
use Yii;
use common\models\Item;
use common\models\ItemPhoto;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
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
     * @return Response|string
     */
    public function actionIndex(): Response|string
    {
        $rootItems = Item::find()->where('parentId IS NULL')->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all();

        return $this->render('index', [
            'rootItems' => $rootItems,
        ]);
    }

    /**
     * @param string|null $id
     * @return Response|string
     */
    public function actionPickContainer(string $id = null): Response|string
    {
        $query = Item::find()->where('isContainer != 0')->orderBy(['priority' => SORT_DESC, 'id' => SORT_ASC]);
        $parentContainer = $id ? (clone $query)->andWhere('id = :containerId', ['containerId' => $id])->one() : null;
        $containers = $id
            ? (clone $query)->andWhere('parentId = :containerId', ['containerId' => $id])->all()
            : (clone $query)->andWhere('parentId IS NULL')->all();
        $this->layout = 'blank';
        return $this->render('pick-container', [
            'parentContainerId' => $id,
            'parentContainer' => $parentContainer,
            'containers' => $containers,
        ]);
    }

    public function actionSearchContainer(string $q): Response|string
    {
        $queryString = Yii::$app->request->getQueryParam('q', '');
        $queryWords = array_filter(preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY), function($value) { return $value !== ''; });
        $containers = [];
        if (count($queryWords) > 0) {
            $query = Item::find()->where('isContainer != 0');
            $i = 0;
            $hasPositiveCondition = false;
            foreach ($queryWords as $queryWord) {
                if ($queryWord[0] !== '-') {
                    $query->leftJoin(["t{$i}" => 'items_tags'], "t{$i}.itemId = id");
                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                    $hasPositiveCondition = true;
                } else {
                    $query->leftJoin(["t{$i}" => 'items_tags'], "t{$i}.itemId = id AND t{$i}.tag LIKE :tagMask{$i}");
                    $queryWord = mb_substr($queryWord, 1);
                    $query->andWhere("t{$i}.tag IS NULL AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                }
                $i++;
            }
            $query->groupBy('items.id');
            if ($hasPositiveCondition) {
                $containers = $query->all();
            }
        }
        $this->layout = 'blank';
        return $this->render('search-container', [
            'containers' => $containers,
            'query' => $queryString,
        ]);
    }

    /**
     * @return Response|string
     * @throws NotFoundHttpException
     */
    public function actionSearch(): Response|string
    {
        $queryString = Yii::$app->request->getQueryParam('q', '');
        $containerId = Yii::$app->request->getQueryParam('c');

        $container = $containerId !== null ? $this->findModel((int) $containerId) : null;

        $queryWords = array_filter(preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY), function($value) { return $value !== ''; });

        $items = [];
        if (count($queryWords) > 0) {
            $query = Item::find();
            $i = 0;
            $hasPositiveCondition = false;
            foreach ($queryWords as $queryWord) {
                if ($queryWord[0] !== '-') {
                    $query->leftJoin(["t{$i}" => 'items_tags'], "t{$i}.itemId = id");
                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                    $hasPositiveCondition = true;
                } else {
                    $query->leftJoin(["t{$i}" => 'items_tags'], "t{$i}.itemId = id AND t{$i}.tag LIKE :tagMask{$i}");
                    $queryWord = mb_substr($queryWord, 1);
                    $query->andWhere("t{$i}.tag IS NULL AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
                }
                $i++;
            }
            $query->groupBy('items.id')
                ->orderBy('items.isContainer DESC, items.id ASC');
//            var_dump($query->createCommand()->getRawSql());die;
            if ($hasPositiveCondition) {
                $items = $query->all();
            }
        }

        $paths = [];
        $tmpItems = [];
        $maxResults = 2000;
        $isMoreThan = false;
        $i = 0;
        foreach ($items as $item) {
            if ($i >= $maxResults) {
                $isMoreThan = true;
                break;
            }
            $doSkipItem = (bool) $containerId;
            $path = $this->getItemPathForView($item);
            if ($containerId) {
                foreach ($path as $pathItem) {
                    if ($pathItem['id'] == $containerId) {
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

        return $this->render('search', [
            'items' => $items,
            'paths' => $paths,
            'query' => $queryString,
            'searchInside' => (bool) $containerId,
            'containerId' => (int) $containerId,
            'container' => $container,
            'isMoreThan' => $isMoreThan,
        ]);
    }

    private function getItemPathForView(Item $item): array
    {
        $path = [];
        $tmpItem = $item;
        while ($tmpItem) {
            $path[] = [
                'id' => $tmpItem->id,
                'label' => $tmpItem->name,
                'url' => ['items/view', 'id' => $tmpItem->id],
            ];
            $tmpItem = $tmpItem->parent;
        }
        return $path;
    }

    /**
     * Displays a single Item model.
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): Response|string
    {
        $model = $this->findModel($id);

        $prevItem = Item::find()->where('id < :id', ['id' => $id])->orderBy('id DESC')->limit(1)->one();
        $nextItem = Item::find()->where('id > :id', ['id' => $id])->orderBy('id ASC')->limit(1)->one();

        return $this->render('view', [
            'model' => $model,
            'children' => $model->getItems()->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all(),
            'containerId' => $id,
            'prevItem' => $prevItem,
            'nextItem' => $nextItem,
        ]);
    }

    /**
     * Creates a new Item model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return Response|string
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionCreate(): Response|string
    {
        $item = new Item();
        $item->priority = 0;

        $tagsForm = new ItemTagsForm();

        $parentId = Yii::$app->request->getQueryParam('parentId');
        if ($parentId) {
            $parent = Item::findOne($parentId);
            if (! $parent) {
                throw new NotFoundHttpException("Parent item #{$parentId} not found");
            }
            $item->parentId = $parentId;
        } else {
            $parent = null;
        }
        $item->isContainer = (bool) Yii::$app->request->getQueryParam('isContainer');

        $goto = Yii::$app->request->post('goto', Yii::$app->request->getQueryParam('goto', 'view'));

        if (Yii::$app->request->isPost) {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($item->load(Yii::$app->request->post()) && $item->save()) {

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
                    ? ['items/create', 'parentId' => $parentId, 'goto' => $goto]
                    : ['view', 'id' => $item->id]);
            }
        }
        return $this->render('create', [
            'model' => $item,
            'parent' => $parent,
            'tagsForm' => $tagsForm,
            'goto' => $goto,
        ]);
    }

    /**
     * Updates an existing Item model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id
     * @return Response|string
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        $item = $this->findModel($id);

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
                return $this->redirect(['view', 'id' => $item->id]);
            }
        }
        return $this->render('update', [
            'model' => $item,
            'tagsForm' => $tagsForm,
        ]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id
     * @return Response|string
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete(int $id): Response|string
    {
        $item = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $parentId = $item->parentId;
            $item->delete();
            return $this->redirect($parentId ? ['items/view', 'id' => $parentId] : ['items/index']);
        } else {
            return $this->render('delete', [
                'model' => $item,
            ]);
        }
    }

    /**
     * Импорт предметов в контейнер
     *
     * @param int $parentId
     * @return Response|string
     * @throws Exception
     * @throws HttpException
     */
    public function actionImport(int $parentId): Response|string
    {
        if (! $parent = Item::findOne($parentId) ) {
            throw new HttpException(404, "Item with ID={$parentId} not found");
        }
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

        if ($confirm && $errorLine === null) {
            foreach ($items as $item) {
                $itemModel = new Item();
                $itemModel->name = $item['name'];
                $itemModel->parentId = $parent->id;
                $itemModel->isContainer = !empty($item['container']) ? '1' : '0';
                if (isset($item['description'])) {
                    $itemModel->description = $item['description'];
                }
                if (!$itemModel->save()) {
                    throw new Exception(ValidateErrorsFormatter::getMessage($itemModel));
                }
                if (isset($item['tags'])) {
                    $itemModel->saveTagsFromString($item['tags']);
                }
            }
            return $this->redirect(['view', 'id' => $parent->id]);
        }

        return $this->render('import', [
            'text' => $text,
            'parent' => $parent,
            'items' => $items,
            'errorLine' => $errorLine,
            'errorStr' => $errorStr,
            'errorMsg' => $errorMsg,
        ]);
    }

    /**
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionJsonPreview(int $id): Response
    {
        $model = $this->findModel($id);
        return $this->asJson([
            'content' => $this->renderPartial('_items', [
                'items' => [$model],
                'paths' => [
                    $model->id => $this->getItemPathForView($model),
                ],
                'showPath' => true,
                'showChildren' => false,
                'containerId' => null,
            ]),
        ]);
    }

    /**
     * Finds the Item model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Item
    {
        if (($model = Item::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException("The requested item #{$id} does not exist");
        }
    }
}
