<?php

use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Repo $repo */

$this->title = 'Создание репозитория';
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'repo' => $repo,
    ]) ?>

</div>
