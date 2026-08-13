<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\User;

/** @var yii\web\View $this */
/** @var backend\models\UserForm $model */
/** @var yii\bootstrap5\ActiveForm $form */
?>

<div class="user-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'username')->textInput(['maxlength' => true]) ?>

    <?php
        $passwordField = $form->field($model, 'password')->passwordInput();
        if (! $model->isNewRecord) {
            $passwordField->hint('Оставьте пустым если не хотите менять пароль');
        }
        echo $passwordField;
    ?>

    <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'status')->dropDownList([
        User::STATUS_ACTIVE => 'ACTIVE',
        User::STATUS_DELETED => 'DELETED',
    ]) ?>

    <div class="mb-3">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
