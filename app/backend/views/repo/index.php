<?php

declare(strict_types=1);

use common\components\UserAccess;
use yii\helpers\Html;
use common\models\Repo;

/** @var yii\web\View $this */
/** @var Repo[] $repos */

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
                <div class="description"><?= $repo->description !== null ? \common\helpers\MarkdownFormatter::format($repo->description, $repo) : ''; ?></div>
            </td>
            <td class="count"><?= $repo->getItems()->count() ?></td>
            <td class="actions">
                <?= Html::a('', \yii\helpers\Url::to(['repo/view', 'repoId' => $repo->id]), ['class' => 'bi bi-eye', 'style' => 'margin: 0 20px', 'title' => 'Просмотреть', 'aria-label' => 'Просмотреть репозиторий']) ?>
                <?= Html::a('', \yii\helpers\Url::to(['repo/update', 'repoId' => $repo->id]), ['class' => 'bi bi-pencil', 'style' => 'margin: 0 20px', 'title' => 'Изменить', 'aria-label' => 'Изменить репозиторий']) ?>
                <?= Html::a('', \yii\helpers\Url::to(['repo/delete', 'repoId' => $repo->id]), ['class' => 'bi bi-trash', 'style' => 'margin: 0 20px', 'title' => 'Удалить', 'aria-label' => 'Удалить репозиторий']) ?>
            </td>
        </tr>
        <?php } ?>
    </table>
    <?php } else { ?>
        <p>Здесь пока ничего нет.</p>
    <?php } ?>

    <?php if (UserAccess::canCreateRepo()) { ?>
    <p><?= Html::a('<i class="bi bi-plus-circle" style="margin-right: 5px;"></i> Добавить репозиторий', ['repo/create'], ['class' => 'btn btn-success']) ?></p>
    <?php } ?>
</div>
