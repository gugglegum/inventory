<?php

declare(strict_types=1);

use yii\helpers\Html;
use common\models\Repo;

/* @var $this yii\web\View */
/* @var $repos Repo[] */

$this->title = 'Репозитории';
$this->registerCssFile('@web/css/repos.css', ['appendTimestamp' => true], 'repos');

?>
<div class="repo-index">
    <h1>Репозитории</h1>
    <?php if (!empty($repos)) { ?>
    <table class="repos">
        <tr>
            <th>ID</th>
            <th>Наименование</th>
            <th>Кол-во предметов</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($repos as $repo) { ?>
        <tr>
            <td class="id"><?= Html::encode($repo->id) ?></td>
            <td class="details">
                <div class="name"><a href="<?= Html::encode(\yii\helpers\Url::to(['items/index', 'repoId' => $repo->id])) ?>"><?= Html::encode($repo->name) ?></a></div>
                <div class="description"><?= Html::encode($repo->description) ?></div>
            </td>
            <td class="count"><?= $repo->getItems()->count() ?></td>
            <td class="actions">
                <?= Html::a('', \yii\helpers\Url::to(['repo/update', 'repoId' => $repo->id]), ['class' => 'glyphicon glyphicon-edit edit-link', 'style' => 'margin-right: 20px']) ?>
                <?= Html::a('', \yii\helpers\Url::to(['repo/delete', 'repoId' => $repo->id]), ['class' => 'glyphicon glyphicon-trash', 'style' => 'margin-left: 20px']) ?>
            </td>
        </tr>
        <?php } ?>
    </table>
    <?php } else { ?>
        <p>Здесь пока ничего нет.</p>
    <?php } ?>

    <p><?= Html::a('<i class="glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Добавить репозиторий', ['repo/create'], ['class' => 'btn btn-success']) ?></p>
</div>
