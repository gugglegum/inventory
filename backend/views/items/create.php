<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Item */
/* @var $parent common\models\Item */
/* @var $tagsForm \backend\models\ItemTagsForm */
/* @var $goto string */

$this->title = 'Создание ' . ($model->isContainer ? 'контейнера' : 'предмета');
$this->render('_breadcrumbs', ['model' => $parent]);
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'tagsForm' => $tagsForm,
        'goto' => $goto,
    ]) ?>

</div>
