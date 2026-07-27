<?php

/** @var yii\web\View $this */
/** @var \common\models\LoginForm $model */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'Вход';
$passwordLoginEnabled = (bool) (Yii::$app->params['auth']['passwordLoginEnabled'] ?? true);
$ssoLoginEnabled = (bool) (Yii::$app->params['auth']['ssoLoginEnabled'] ?? false);
?>
<div class="site-login">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($ssoLoginEnabled): ?>
        <p>Используйте для входа учётную запись pyrda.ru.</p>

        <p>
            <?= Html::a(
                'Войти через Pyrda SSO',
                ['/site/sso-login'],
                ['class' => 'btn btn-primary btn-lg']
            ) ?>
        </p>
    <?php endif; ?>

    <?php if ($passwordLoginEnabled): ?>
        <?php if ($ssoLoginEnabled): ?>
            <hr>
        <?php endif; ?>

        <h2>Вход по паролю</h2>

        <div class="row">
            <div class="col-lg-5">
                <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

                    <?= $form->field($model, 'username') ?>

                    <?= $form->field($model, 'password')->passwordInput() ?>

                    <?= $form->field($model, 'rememberMe')->checkbox() ?>

                    <div class="form-group">
                        <?= Html::submitButton(
                            'Войти по паролю',
                            ['class' => 'btn btn-default', 'name' => 'login-button']
                        ) ?>
                    </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
