<?php

declare(strict_types=1);

use common\models\Item;
use common\models\Post;
use common\models\Repo;
use yii\bootstrap5\LinkPager;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var ActiveDataProvider $dataProvider */
/** @var Item $item */
/** @var Repo $repo */

$this->title = 'Заметки — ' . $item->name;
$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => ['Заметки']]);
$this->render('//_fancybox');

/** @var Post[] $posts */
$posts = $dataProvider->getModels();
?>
<div class="post-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="post-index__meta">
        <span class="text-muted">Всего заметок: <?= $dataProvider->getTotalCount() ?></span>
        <?= Html::a('<i class="bi bi-plus-circle"></i> Добавить заметку', ['posts/create', 'repoId' => $repo->id, 'itemId' => $item->itemId]) ?>
    </div>

    <?php if ($posts !== []) { ?>
        <?= $this->render('_posts', ['posts' => $posts, 'item' => $item, 'repo' => $repo]) ?>
        <?= LinkPager::widget(['pagination' => $dataProvider->getPagination()]) ?>
    <?php } else { ?>
        <p class="hint-block"><em>Нет заметок</em></p>
    <?php } ?>
</div>
