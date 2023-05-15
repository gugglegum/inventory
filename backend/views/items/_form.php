<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Item */
/* @var $form yii\widgets\ActiveForm */
/* @var $tagsForm \backend\models\ItemTagsForm */
/* @var $goto string */

$this->registerJsFile('@web/js/upload_photo.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/item-form.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/item-form.css', [], 'item-form');
$this->registerCssFile('@web/css/upload_photo.css', [], 'upload_photo');

?>

<div class="item-form" style="margin-bottom: 10em;">

    <?php $form = ActiveForm::begin([
        'options' => ['enctype' => 'multipart/form-data'],
        'id' => 'ItemForm',
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true, 'tabindex' => 1]) ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 4, 'tabindex' => 2]) ?>
    <?= $form->field($model, 'parentId')->textInput(['maxlength' => true, 'tabindex' => 3]) ?>
    <button type="button" id="btnTogglePickContainerModal" class="btn btn-primary" data-toggle="modal" data-target="#pickContainerModal">Выбрать...</button>
    <?= $form->field($tagsForm, 'tags')->textInput(['tabindex' => 4]) ?>
    <?= $form->field($model, 'isContainer')->checkbox(['tabindex' => 5]) ?>

    <label class="control-label">Фотографии</label>
    <?php
        $photos = $model->itemPhotos;
        if (count($photos) !== 0) {
            echo Html::beginTag('div', ['class' => 'uploaded-photos']);

            foreach ($photos as $itemPhoto) {
                /** @var \common\models\ItemPhoto $itemPhoto */
                echo Html::beginTag('div', ['class' => 'photo-wrapper']);
                echo '<button type="button" class="btn btn-mini btn-delete" data-action="' . Html::encode(Url::to(['photo/delete'])) . '" data-id="' . $itemPhoto->id . '"><i class="glyphicon glyphicon-trash"></i></button>';
                echo '<button type="button" class="btn btn-mini btn-sort-up" data-action="' . Html::encode(Url::to(['photo/sort-up'])) . '" data-id="' . $itemPhoto->id . '"><i class="glyphicon glyphicon-arrow-up"></i></button>';
                echo '<button type="button" class="btn btn-mini btn-sort-down" data-action="' . Html::encode(Url::to(['photo/sort-down'])) . '" data-id="' . $itemPhoto->id . '"><i class="glyphicon glyphicon-arrow-down"></i></button>';
                echo Html::beginTag('a', ['href' => $itemPhoto->getUrl(), 'rel' => 'item-photos', 'class' => 'fancybox']);
                echo Html::img($itemPhoto->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Photo']);
                echo Html::endTag('a');
                echo Html::endTag('div');
            }

            echo '<div class="clearfix"></div>';
            echo Html::endTag('div');
        }
    ?>

    <label class="control-label">Добавить фотографии</label>
    <ol class="form-group" id="PhotosContainer">
        <li class="field-item-photos">
            <!--<div class="block" style="">
                <img />
            </div>-->
            <div class="clearfix"></div>
            <input class="custom-file-input" type="file" name="photos[]" tabindex="6" />
        </li>
    </ol>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => 7]) ?>
    </div>

    <?php if ($model->isNewRecord) { ?>
    <div>
        <label for="goto">После создания:</label>
        <select id="goto" name="goto">
        <?= Html::renderSelectOptions($goto, [
            'view' => 'перейти к просмотру',
            'create' => 'перейти к созданию ещё одного',
        ]) ?>
        </select>
    </div>
    <?php } ?>

    <?php ActiveForm::end(); ?>

</div>
<!-- Modal -->
<div class="modal fade" id="pickContainerModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Выбор родительского контейнера</h4>
            </div>
            <div class="modal-body" data-iframe-base-src="<?= Html::encode(Url::to(['items/pick-container', 'id' => ''])) ?>">
            </div>
        </div>
    </div>
</div>
