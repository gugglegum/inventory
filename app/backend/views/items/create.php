<?php

use backend\models\ItemTagsForm;
use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Item $model */
/** @var Item $parent */
/** @var Repo $repo */
/** @var ItemTagsForm $tagsForm */
/** @var string $goto */

$this->title = 'Создание ' . ($model->isContainer ? 'контейнера' : 'предмета');
$this->render('/_breadcrumbs', ['item' => $parent, 'repo' => $repo, 'suffix' => [$this->title]]);

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)
?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'repo' => $repo,
        'tagsForm' => $tagsForm,
        'goto' => $goto,
    ]) ?>

</div>
