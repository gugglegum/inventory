<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Repo $repo */

//$this->registerJsFile('@web/js/item-form.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'item-form');
//$this->registerCssFile('@web/css/items.css', ['appendTimestamp' => true], 'items');
//$this->registerCssFile('@web/css/item-form.css', ['appendTimestamp' => true], 'item-form');
//$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');

/** @var \yii\widgets\ActiveForm $form */
$tabIndex = 1;
?>

<div class="item-form" style="margin-bottom: 10em;">

    <?php $form = ActiveForm::begin([
        'options' => [/*'enctype' => 'multipart/form-data', 'data-repo-id' => $repo->id*/],
        'id' => 'RepoForm',
    ]); ?>

    <?= $form->errorSummary($repo) ?>

    <?= $form->field($repo, 'name')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($repo, 'description')->textarea(['rows' => 4, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($repo, 'priority')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($repo, 'lastItemId')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>

    <div class="form-group">
        <?= Html::submitButton($repo->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $repo->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['repo/index']), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if (!$repo->isNewRecord) { ?>
            <?= Html::a('<i class="glyphicon glyphicon-trash"></i> Удалить', ['delete', 'repoId' => $repo->id, 'id' => $repo->id], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
