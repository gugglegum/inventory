<?php

namespace backend\controllers;

use backend\models\ItemTagsForm;
use common\helpers\ValidateErrorsFormatter;
use Yii;
use common\models\Item;
use common\models\ItemPhoto;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ItemsController implements the CRUD actions for Item model.
 */
class ItemsController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'import' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Item models.
     * @return mixed
     * @throws InvalidParamException if the view file or the layout file does not exist.
     */
    public function actionIndex()
    {
        $rootItems = Item::find()->where('parentId IS NULL')->orderBy(['id' => SORT_ASC])->all();

        return $this->render('index', [
            'rootItems' => $rootItems,
        ]);
    }

    public function actionSearch()
    {
        $queryString = Yii::$app->request->getQueryParam('q', '');

        $queryWords = array_filter(preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY), function($value) { return $value !== ''; });

        $items = [];
        if (count($queryWords) > 0) {
            $query = Item::find();
            $i = 0;
            $hasPositiveCondition = false;
            foreach ($queryWords as $queryWord) {
//                $query->joinWith('itemTags');
                $query->leftJoin(["t{$i}" => 'items_tags'], "t{$i}.itemId = id");
                if ($queryWord[0] !== '-') {
                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
//                    $query->andWhere("t{$i}.tag LIKE :tagMask{$i}", [/*"tag{$i}" => $queryWord, */"tagMask{$i}" => '%' . $queryWord . '%']);
                    $hasPositiveCondition = true;
                } else {
                    $queryWord = mb_substr($queryWord, 1);
                    $query->andWhere("t{$i}.tag NOT LIKE :tagMask{$i} AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}", ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']);
//                    $query->andWhere("t{$i}.tag NOT LIKE :tagMask{$i}", [/*"tag{$i}" => $queryWord, */"tagMask{$i}" => '%' . $queryWord . '%']);
                }
                $i++;
            }

//            var_dump($query->createCommand()->getRawSql());die;

            if ($hasPositiveCondition) {
                $items = $query->all();
            }
        }

        return $this->render('search', [
            'items' => $items,
            'query' => $queryString,
        ]);
    }

    /**
     * Displays a single Item model.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException
     * @throws InvalidParamException if the view file or the layout file does not exist.
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'parent' => $model->parentId ? $this->findModel($model->parentId) : null,
            'children' => $model->items,
        ]);
    }

    /**
     * Creates a new Item model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     * @throws InvalidParamException if the view file or the layout file does not exist.
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     * @throws NotFoundHttpException
     */
    public function actionCreate()
    {
        $item = new Item();

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
        if ($isContainer = (bool) Yii::$app->request->getQueryParam('isContainer')) {
            $item->isContainer = $isContainer;
        }

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
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws InvalidParamException if the view file or the layout file does not exist.
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function actionUpdate($id)
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
                    if ($photoValue === '') {
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
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Exception
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $parentId = $model->parentId;
        $model->delete();
        return $this->redirect($parentId ? ['items/view', 'id' => $parentId] : ['items/index']);
    }

    /**
     * Импорт предметов в контейнер
     *
     * @param int $parentId
     * @return string
     * @throws InvalidParamException if the view file or the layout file does not exist.
     * @throws Exception
     * @throws HttpException
     */
    public function actionImport($parentId)
    {
        if (! $parent = Item::findOne($parentId) ) {
            throw new HttpException(404, "Item with ID={$parentId} not found");
        }
        $text = Yii::$app->request->post('text');
        $confirm = (bool) Yii::$app->request->post('confirm');
        $items = [];

        $errorLine = null;
        $errorStr = null;

        $line = 1;
        $item = [];
        foreach (explode("\n", $text) as $str) {
            $str = trim($str);

            if ($str === '') {
                continue;
            }

            if ($str{0} !== '*') {
                if (isset($item['name'])) {
                    $items[] = $item;
                    $item = [];
                }
                $item['name'] = $str;
            } else {
                if (preg_match('/^\*\s*(tags|description|container)\s*:\s*(.*)$/i', $str, $m)) {
                    $item[trim($m[1])] = trim($m[2]);
                } else {
                    $errorLine = $line;
                    $errorStr = $str;
                    break;
                }
            }

            $line++;
        }
        if (isset($item['name'])) {
            $items[] = $item;
        }

        if ($confirm) {
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
        ]);
    }

    /**
     * Finds the Item model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Item::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
