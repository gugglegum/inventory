<?php

use common\models\Repo;
use common\models\Item;
use common\models\Post;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Post $post */
/** @var Item $item */
/** @var Repo $repo */

$this->title = $post->title;
$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => [
        ['url' => \yii\helpers\Url::to(['posts/view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]), 'label' => $this->title],
        'Редактирование'
    ]
]);

?>
<div class="repo-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'post' => $post,
        'item' => $item,
        'repo' => $repo,
    ]) ?>

</div>
