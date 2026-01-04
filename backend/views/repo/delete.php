<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Repo $repo */

$this->title = 'Удаление репозитория';

$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-delete">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>Вы собираетесь удалить этот репозиторий<?= ($itemsCount = $repo->getItems()->count()) > 0 ? ', содержащий предметов (<strong>' . $itemsCount . '</strong>)' : '' ?>:</p>

    <?php $form = ActiveForm::begin([
        'action' => Url::to(['delete', 'repoId' => $repo->id]),
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]); ?>

    <?= $form->errorSummary($repo) ?>

    <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'Вы действительно хотите удалить этот репозиторий?',
            'method' => 'post',
        ],
    ]) ?>
    <?php ActiveForm::end(); ?>

</div>
