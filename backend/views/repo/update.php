<?php

use backend\models\RepoForm;
use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Repo $repo */
/** @var RepoForm $repoForm */

$this->title = $repo->name;
$this->render('/_breadcrumbs', ['item' => null, 'repo' => null, 'suffix' => [
        ['label' => $repo->name, 'url' => ['repo/view', 'repoId' => $repo->id]],
        'Редактирование',
    ]
]);

?>
<div class="repo-update">

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #999"><?= Html::encode($repo->id) ?></sup></h1>

    <?= $this->render('_form', [
        'repoForm' => $repoForm,
    ]) ?>

</div>
