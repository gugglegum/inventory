<?php

use backend\models\PostForm;
use backend\models\PhotoEditorForm;
use common\models\PhotoUploadSession;
use kartik\datetime\DateTimePicker;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\ActiveForm;

/** @var \yii\web\View $this */
/** @var PostForm $postForm */
/** @var \common\models\Item $item */
/** @var \common\models\Repo $repo */
/** @var PhotoEditorForm $photoEditorForm */
/** @var array $photoEntries */


/** @var \yii\bootstrap5\ActiveForm $form */
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
            'pickerIcon' => '<i class="bi bi-calendar3 kv-dp-icon"></i>',
            'pluginOptions' => [
                'format' => 'dd.mm.yyyy hh:ii',
                'fontAwesome' => false,
                'icontype' => 'bi',
                'icons' => [
                    'leftArrow' => 'bi-chevron-left',
                    'rightArrow' => 'bi-chevron-right',
                ],
            ],
            'removeButton' => false,
            'layout' => '{picker}{remove}{input} <span style="position: relative; top: 0.5em; left: 0.5em;">(UTC)</span>',
    ]) ?>
    <?= $form->field($postForm, 'title')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($postForm, 'text')->textarea(['rows' => 4, 'tabindex' => $tabIndex++]) ?>

    <label class="form-label">Фотографии</label>
    <?= $this->render('//_photo-editor', [
        'photoEditorForm' => $photoEditorForm,
        'photoEntries' => $photoEntries,
        'createSessionUrl' => Url::to(['photo-upload/create']),
        'repoId' => (int) $repo->id,
        'uploadContext' => PhotoUploadSession::CONTEXT_POST,
        'uploadUrl' => $photoEditorForm->sessionToken === '' ? '' : Url::to([
            'photo-upload/upload',
            'token' => $photoEditorForm->sessionToken,
            'repoId' => (int) $repo->id,
            'context' => PhotoUploadSession::CONTEXT_POST,
        ]),
        'maxUploadBytes' => (int) (Yii::$app->params['photos']['maxUploadBytes'] ?? 0),
    ]) ?>

    <div class="mb-3">
        <?= Html::submitButton($post->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $post->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="bi bi-x-lg"></i> Отмена', Url::to(
                $post->isNewRecord ? ['items/view', 'repoId' => $repo->id, 'itemId' => $item->itemId] : ['view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]
        ), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if (!$post->isNewRecord) { ?>
            <?= Html::a('<i class="bi bi-trash"></i> Удалить', ['delete', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
