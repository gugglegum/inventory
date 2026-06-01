<?php

use backend\models\RepoForm;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var RepoForm $repoForm */

$this->title = 'Создание репозитория';
$this->render('/_breadcrumbs', ['item' => null, 'repo' => null, 'suffix' => [$this->title]]);

?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'repoForm' => $repoForm,
    ]) ?>

</div>
