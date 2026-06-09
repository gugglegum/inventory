<?php

use backend\models\PostForm;
use kartik\datetime\DateTimePicker;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var PostForm $postForm */
/** @var \common\models\Item $item */
/** @var \common\models\Repo $repo */

$this->registerJsFile('@web/js/upload_photo.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'upload_photo');
$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');

/** @var \yii\widgets\ActiveForm $form */
$tabIndex = 1;
$post = $postForm->getPost();
?>

<div class="item-form" style="margin-bottom: 10em;">

    <?php $form = ActiveForm::begin([
        'action' => $post->isNewRecord
                ? Url::to(['posts/create', 'repoId' => $repo->id, 'itemId' => $item->itemId])
                : Url::to(['posts/update', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]),
        'options' => ['enctype' => 'multipart/form-data'],
        'id' => 'PostForm',
    ]); ?>

    <?= $form->errorSummary($postForm) ?>

    <?= $form->field($postForm, 'datetimeText')->widget(DateTimePicker::class, [
            'options' => [
                'placeholder' => 'ДД.ММ.ГГГГ ЧЧ:ММ',
                'style' => 'width: 150px',
            ],
            'pluginOptions' => [
                'format' => 'dd.mm.yyyy hh:ii',
            ],
            'removeButton' => false,
            'layout' => '{picker}{remove}{input} <span style="position: relative; top: 0.5em; left: 0.5em;">(UTC)</span>',
    ]) ?>
    <?= $form->field($postForm, 'title')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($postForm, 'text')->textarea(['rows' => 4, 'tabindex' => $tabIndex++]) ?>

    <label class="control-label">Фотографии</label>
    <?php
        $photos = $post->postPhotos;
        if (count($photos) !== 0) {
            echo Html::beginTag('div', ['class' => 'uploaded-photos']);

            foreach ($photos as $postPhoto) {
                /** @var \common\models\PostPhoto $postPhoto */
                echo Html::beginTag('div', ['class' => 'photo-wrapper']);
                echo Html::beginTag('div', ['class' => 'photo-frame']);
                echo '<button type="button" class="btn btn-mini btn-delete" data-action="' . Html::encode(Url::to(['photo/delete'])) . '" data-id="' . $postPhoto->id . '" data-photo-type="post"><i class="glyphicon glyphicon-trash"></i></button>';
                echo '<button type="button" class="btn btn-mini btn-sort-up" data-action="' . Html::encode(Url::to(['photo/sort-up'])) . '" data-id="' . $postPhoto->id . '" data-photo-type="post"><i class="glyphicon glyphicon-arrow-up"></i></button>';
                echo '<button type="button" class="btn btn-mini btn-sort-down" data-action="' . Html::encode(Url::to(['photo/sort-down'])) . '" data-id="' . $postPhoto->id . '" data-photo-type="post"><i class="glyphicon glyphicon-arrow-down"></i></button>';
                echo Html::beginTag('a', ['href' => $postPhoto->photo->getUrl(), 'rel' => 'post-photos', 'class' => 'fancybox']);
                echo Html::img($postPhoto->photo->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Photo']);
                echo Html::endTag('a');
                echo Html::endTag('div');
                echo Html::endTag('div');
            }

            echo '<div class="clearfix"></div>';
            echo Html::endTag('div');
        }
    ?>

    <label class="control-label">Добавить фотографии</label>
    <ol class="form-group" id="PhotosContainer">
        <li class="field-item-photos">
            <input class="custom-file-input" type="file" name="photos[]" tabindex="<?= $tabIndex++ ?>" />
        </li>
    </ol>

    <div class="form-group">
        <?= Html::submitButton($post->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $post->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(
                $post->isNewRecord ? ['items/view', 'repoId' => $repo->id, 'itemId' => $item->itemId] : ['view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]
        ), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if (!$post->isNewRecord) { ?>
            <?= Html::a('<i class="glyphicon glyphicon-trash"></i> Удалить', ['delete', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
