<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Item $model */
/** @var Repo $repo */

$this->title = 'Удаление ' . ($model->isContainer ? 'контейнера' : 'предмета');
$this->render('/_breadcrumbs', ['item' => $model, 'repo' => $repo, 'suffix' => [$this->title]]);

?>
<div class="item-delete">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>Вы собираетесь удалить этот <?= $model->isContainer ? 'контейнер' : 'предмет' ?><?= ($childrenCount = $model->getItems()->count()) > 0 ? ', включая вложенные предметы (<strong>' . $childrenCount . '</strong>)' : '' ?>:</p>

    <?= $this->render('_items', [
        'items' => [$model],
        'showPath' => false,
        'showChildren' => true,
        'containerId' => null,
        'repo' => $repo,
    ]) ?>

    <?php $form = ActiveForm::begin([
        'action' => Url::to(['delete', 'repoId' => $repo->id, 'itemId' => $model->itemId]),
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]); ?>

    <?= $form->errorSummary($model) ?>

    <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'Вы действительно хотите удалить этот ' . ($model->isContainer ? 'контейнер' : 'предмет') . '?',
            'method' => 'post',
        ],
    ]) ?>
    <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['items/view', 'repoId' => $repo->id, 'itemId' => $model->itemId]), ['style' => 'margin-left: 1em']) ?>
    <?php ActiveForm::end(); ?>

</div>
