<?php

use common\models\Post;
use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Post $post */
/** @var Item $item */
/** @var Repo $repo */

$this->title = $post->title;
$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => [
        ['url' => \yii\helpers\Url::to(['posts/view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]), 'label' => $this->title],
        'Редактирование'
]]);

?>
<div class="item-delete">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'action' => Url::to(['delete', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]),
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]); ?>

    <?= $form->errorSummary($post) ?>

    <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'Вы действительно хотите удалить этот пост?',
            'method' => 'post',
        ],
    ]) ?>
    <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['items/view', 'repoId' => $repo->id, 'id' => $item->itemId]), ['style' => 'margin-left: 1em']) ?>
    <?php ActiveForm::end(); ?>

</div>
