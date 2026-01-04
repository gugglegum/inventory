<?php

use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Repo $repo */

$this->title = $repo->name;
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo, 'suffix' => ['Редактирование']]);

?>
<div class="repo-update">

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #999"><?= Html::encode($repo->id) ?></sup></h1>

    <?= $this->render('_form', [
        'repo' => $repo,
    ]) ?>

</div>
