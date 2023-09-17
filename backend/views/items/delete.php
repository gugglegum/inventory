<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Item */

$this->title = 'Удаление ' . ($model->isContainer ? 'контейнера' : 'предмета');

$this->render('_breadcrumbs', ['model' => $model]);
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-delete">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>Вы собираетесь удалить этот <?= $model->isContainer ? 'контейнер' : 'предмет' ?><?= ($childrenCount = $model->getItems()->count()) > 0 ? ', включая вложенные объекты (<strong>' . $childrenCount . '</strong>)' : '' ?>:</p>

    <?= $this->render('_items', [
        'items' => [$model],
        'showPath' => false,
        'showChildren' => true,
        'containerId' => null,
    ]) ?>

    <?php $form = ActiveForm::begin([
        'action' => Url::to(['delete', 'id' => $model->id]),
        'method' => 'post',
        'options' => [
            'enctype' => 'multipart/form-data',
            'style' => 'margin-top: 1em',
        ],
    ]); ?>
    <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'Вы действительно хотите удалить этот ' . ($model->isContainer ? 'контейнер' : 'предмет') . '?',
            'method' => 'post',
        ],
    ]) ?>
    <?php ActiveForm::end(); ?>

</div>
