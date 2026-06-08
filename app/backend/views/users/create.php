<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var backend\models\UserForm $model */

$this->title = 'Create User';
$this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index'], 'suffix' => [$this->title]];
?>
<div class="user-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
