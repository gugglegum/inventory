<?php

use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Repo $repo */

$this->title = $repo->name;

$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);

$this->registerCssFile('@web/css/repo-view.css', ['appendTimestamp' => true], 'repo-view');

$description = trim((string) $repo->description);
if ($description !== '') {
    $description = \common\helpers\MarkdownFormatter::format($description, $repo);
} else {
    $description = '<em>Нет описания</em>';
}

?>
<div id="repo-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <dl id="repo-description">
        <div id="lnkEdit">
            <?= Html::a('<i class="glyphicon glyphicon-edit" style="margin-right: 5px;"></i> Изменить', ['update', 'repoId' => $repo->id]) ?>
        </div>
        <dt>Описание</dt>
        <dd><?= $description ?></dd>
    </dl>

    <div class="columns-container">
        <div id="repo-info">
            <dl>
                <dt>Приоритет сортировки:</dt>
                <dd><?= Html::encode($repo->priority) ?></dd>
            </dl>
            <dl>
                <dt>ID последнего предмета:</dt>
                <dd><?= Html::encode($repo->lastItemId) ?></dd>
            </dl>
            <dl>
                <dt>Создатель:</dt>
                <dd><?= $repo->createdByUser ? Html::encode($repo->createdByUser->username) : '<em>Неизвестно</em>' ?></dd>
            </dl>
            <dl>
                <dt>Дата создания:</dt>
                <dd><?= Html::encode(date('d.m.Y H:i T', $repo->created)) ?></dd>
            </dl>
            <dl>
                <dt>Последним изменил(а):</dt>
                <dd><?= $repo->updatedByUser ? Html::encode($repo->updatedByUser->username) : (($repo->updated !== null) ? '<em>Неизвестно</em>' : '<em>Никто</em>') ?></dd>
            </dl>
            <dl>
                <dt>Дата изменения:</dt>
                <dd><?= $repo->updated !== null ? Html::encode(date('d.m.Y H:i T', $repo->updated)) : '<em>Не было изменений</em>' ?></dd>
            </dl>
            <dl>
                <dt>Предметов в репозитории:</dt>
                <dd><?= $repo->getItems()->count() ?></dd>
            </dl>
            <dl>
                <dt>Доступ имеют следующие пользователи:</dt>
                <dd>
                    <table class="repo-users">
                        <tr>
                            <th>Пользователь</th>
                            <th>Права доступа</th>
                            <th>Создано предметов</th>
                        </tr>
                        <?php foreach ($repo->getRepoUsers()->innerJoinWith('user')->where(['user.status' => \common\models\User::STATUS_ACTIVE])->each() as $repoUser) {
                            echo '<tr><td class="username">' . Html::encode($repoUser->user->username) . '</td>'
                                    . '<td class="permissions">'
                                    . '<input type="checkbox" ' . ($repoUser->access & \common\models\RepoUser::ACCESS_CREATE_ITEMS ? 'checked="checked"' : '') . ' disabled="disabled"> Создание предметов<br>' . "\n"
                                    . '<input type="checkbox" ' . ($repoUser->access & \common\models\RepoUser::ACCESS_EDIT_ITEMS ? 'checked="checked"' : '') . ' disabled="disabled"> Редактирование предметов<br>' . "\n"
                                    . '<input type="checkbox" ' . ($repoUser->access & \common\models\RepoUser::ACCESS_DELETE_ITEMS ? 'checked="checked"' : '') . ' disabled="disabled"> Удаление предметов<br>' . "\n"
                                    . '<input type="checkbox" ' . ($repoUser->access & \common\models\RepoUser::ACCESS_EDIT_REPO ? 'checked="checked"' : '') . ' disabled="disabled"> Редактирование репозитория<br>' . "\n"
                                    . '<input type="checkbox" ' . ($repoUser->access & \common\models\RepoUser::ACCESS_DELETE_REPO ? 'checked="checked"' : '') . ' disabled="disabled"> Удаление репозитория<br>' . "\n"
                                    .'</td>'
                                    . '<td class="count">' . $repoUser->user->getCreatedItems()->where(['repoId' => $repo->id])->count() . "</td></tr>\n";
                        } ?></table>
                </dd>
            </dl>
        </div>
    </div>

    <div class="clearfix"></div>

    <p style="margin-top: 3em">
        <?= Html::a('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', ['delete', 'repoId' => $repo->id]) ?>
    </p>
</div>
