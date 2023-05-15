<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Item */
/* @var $tagsForm \backend\models\ItemTagsForm */

$this->title = $model->name;
$this->render('_breadcrumbs', ['model' => $model]);
$this->params['breadcrumbs'][] = 'Редактирование';

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<div class="item-update">

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #ccc">#<?= Html::encode($model->id) ?></sup></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'tagsForm' => $tagsForm,
        'goto' => null,
    ]) ?>

</div>
