<?php

use backend\models\ItemForm;
use backend\models\ItemTagsForm;
use backend\models\PhotoEditorForm;
use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var ItemForm $model */
/** @var Repo $repo */
/** @var ItemTagsForm $tagsForm */
/** @var PhotoEditorForm $photoEditorForm */
/** @var array $photoEntries */

$item = $model->getItem();
$this->title = $model->name;
$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => ['Редактирование']]);

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<div class="item-update">

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #999"><?= Html::encode($item->repoId) ?>#<?= Html::encode($model->itemId) ?></sup></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'repo' => $repo,
        'tagsForm' => $tagsForm,
        'goto' => null,
        'photoEditorForm' => $photoEditorForm,
        'photoEntries' => $photoEntries,
    ]) ?>

</div>
