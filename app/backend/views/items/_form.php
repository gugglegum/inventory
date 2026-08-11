<?php

use backend\models\ItemForm;
use backend\models\ItemTagsForm;
use backend\models\PhotoEditorForm;
use common\models\Item;
use common\models\PhotoUploadSession;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var ItemForm $model */
/** @var Repo $repo */
/** @var ItemTagsForm $tagsForm */
/** @var string $goto */
/** @var PhotoEditorForm $photoEditorForm */
/** @var array $photoEntries */

$this->registerJsFile('@web/js/item-form.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'item-form');
$this->registerCssFile('@web/css/items.css', ['appendTimestamp' => true], 'items');
$this->registerCssFile('@web/css/item-form.css', ['appendTimestamp' => true], 'item-form');

/** @var \yii\widgets\ActiveForm $form */
$tabIndex = 1;
$item = $model->getItem();
?>

<div class="item-form" style="margin-bottom: 10em;">

    <?php $form = ActiveForm::begin([
        'options' => ['enctype' => 'multipart/form-data', 'data-repo-id' => $repo->id],
        'id' => 'ItemForm',
    ]); ?>

    <?= $form->errorSummary($model) ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 4, 'tabindex' => $tabIndex++]) ?>
    <?= $form->field($model, 'parentItemId')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <button type="button" style="float: left" id="btnTogglePickContainerModal" class="btn" data-toggle="modal" data-target="#pickContainerModal" tabindex="<?= $tabIndex++ ?>">Сменить...</button>
    <div id="divParentPreview"></div>
    <div class="clearfix"></div>
    <?= $form->field($tagsForm, 'tags')->textInput(['tabindex' => $tabIndex++]) ?>
    <?= $form->field($model, 'isContainer')->checkbox(['tabindex' => $tabIndex++]) ?>
    <?= $form->field($model, 'priority')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?php if (!$model->isNewRecord) { ?>
        <?= $form->field($model, 'itemId')->textInput(['maxlength' => true, 'tabindex' => $tabIndex++]) ?>
    <?php } ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(
            $model->isNewRecord
                ? $model->parentItemId !== '' ? ['items/view', 'repoId' => $repo->id, 'itemId' => $model->parentItemId] : ['items/index']
                : ['items/view', 'repoId' => $repo->id, 'itemId' => $model->itemId]
        ), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if (!$model->isNewRecord) { ?>
            <?= Html::a('<i class="glyphicon glyphicon-trash"></i> Удалить', ['delete', 'repoId' => $repo->id, 'itemId' => $model->itemId], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <label class="control-label">Фотографии</label>
    <?= $this->render('//_photo-editor', [
        'photoEditorForm' => $photoEditorForm,
        'photoEntries' => $photoEntries,
        'createSessionUrl' => Url::to(['photo-upload/create']),
        'repoId' => (int) $repo->id,
        'uploadContext' => $model->isNewRecord
            ? PhotoUploadSession::CONTEXT_ITEM_CREATE
            : PhotoUploadSession::CONTEXT_ITEM_UPDATE,
        'uploadUrl' => $photoEditorForm->sessionToken === '' ? '' : Url::to([
            'photo-upload/upload',
            'token' => $photoEditorForm->sessionToken,
            'repoId' => (int) $repo->id,
            'context' => $model->isNewRecord
                ? PhotoUploadSession::CONTEXT_ITEM_CREATE
                : PhotoUploadSession::CONTEXT_ITEM_UPDATE,
        ]),
        'maxUploadBytes' => (int) (Yii::$app->params['photos']['maxUploadBytes'] ?? 0),
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Создать' : 'Сохранить', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary', 'tabindex' => $tabIndex++]) ?>
        <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(
            $model->isNewRecord
                ? $model->parentItemId !== '' ? ['items/view', 'repoId' => $repo->id, 'itemId' => $model->parentItemId] : ['items/index']
                : ['items/view', 'repoId' => $repo->id, 'itemId' => $model->itemId]
        ), ['tabindex' => $tabIndex++, 'style' => 'margin-left: 1em']) ?>
        <?php if (!$model->isNewRecord) { ?>
            <?= Html::a('<i class="glyphicon glyphicon-trash"></i> Удалить', ['delete', 'repoId' => $repo->id, 'itemId' => $model->itemId], [
                'style' => 'margin-left: 1em',
                'tabindex' => $tabIndex++,
            ]) ?>
        <?php } ?>
    </div>

    <?php if ($model->isNewRecord) { ?>
    <div>
        <label for="goto">После создания:</label>
        <select id="goto" name="goto" tabindex="<?= $tabIndex++ ?>">
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
            <div class="modal-body" data-iframe-base-src="<?= Html::encode(Url::to(['items/pick-container', 'repoId' => $repo->id, 'itemId' => '0'])) ?>">
            </div>
        </div>
    </div>
</div>
