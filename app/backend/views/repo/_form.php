<?php

use backend\models\RepoForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var RepoForm $repoForm */

//$this->registerJsFile('@web/js/item-form.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'item-form');
//$this->registerCssFile('@web/css/items.css', ['appendTimestamp' => true], 'items');
//$this->registerCssFile('@web/css/item-form.css', ['appendTimestamp' => true], 'item-form');
//$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');

/** @var \yii\widgets\ActiveForm $form */
$tabIndex = 1;
?>

<div class="item-form" style="margin-bottom: 10em;">

    <h3>Общие настройки</h3>

    <?php $form = ActiveForm::begin([
        'options' => [/*'enctype' => 'multipart/form-data', 'data-repo-id' => $repo->id*/],
        'id' => 'RepoForm',
    ]); ?>

    <?= $form->errorSummary($repoForm) ?>

    <?= $form->field($repoForm, 'name')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($repoForm, 'description')->textarea(['rows' => 4, 'tabindex' => $tabIndex++]) ?>
    <?php if ($repoForm->scenario !== RepoForm::SCENARIO_CREATE) { ?>
    <?= $form->field($repoForm, 'lastItemId')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?php } ?>

    <h3>Персональные настройки</h3>

    <?= $form->field($repoForm, 'priority')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>


    <div class="form-group">
        <?= Html::submitButton($repoForm->scenario === RepoForm::SCENARIO_CREATE ? 'Создать' : 'Сохранить', ['class' => $repoForm->scenario === RepoForm::SCENARIO_CREATE ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['repo/index']), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if ($repoForm->scenario !== RepoForm::SCENARIO_CREATE) { ?>
            <?= Html::a('<i class="glyphicon glyphicon-trash"></i> Удалить', ['delete', 'repoId' => $repoForm->getRepo()->id], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
