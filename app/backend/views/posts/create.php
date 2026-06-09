<?php

use backend\models\PostForm;
use common\models\Repo;
use common\models\Item;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var PostForm $postForm */
/** @var Item $item */
/** @var Repo $repo */

$this->title = 'Создание заметки';
$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => [$this->title]]);

?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
            'postForm' => $postForm,
            'item' => $item,
            'repo' => $repo,
    ]) ?>

</div>
