<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $parent common\models\Item */
/* @var $text string */

?>
<?= Html::beginForm(Url::to(['items/import', 'parentId' => $parent->id]), 'post', ['style' => "margin: 1.5em 0;"]) ?>
    <div>
        <label for="text">Импорт из текста:</label><br />
        <?= Html::textarea('text', $text, ['cols' => 100, 'rows' => 12, 'style' => 'font-size: 90%']) ?><br />
        <?= Html::checkbox('confirm', false, ['label' => 'Подтвердить добавление']) ?><br />
        <?= Html::submitInput('Импорт') ?>
    </div>
<?= Html::endForm() ?>
