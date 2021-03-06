<?php

use yii\helpers\Html;
use common\models\Item;

/* @var $this yii\web\View */
/* @var $items Item[] */
/* @var $query string */

$this->title = 'Поиск';
$this->render('_breadcrumbs', ['model' => null]);
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-search">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_searchForm', ['query' => $query]) ?>

    <?php if ($query !== '') { ?>
    <h3>Результаты поиска</h3>

    <?= $this->render('_items', [
        'items' => $items,
        'isSearch' => true,
    ]) ?>
    <?php } ?>

</div>
