<?php

use yii\helpers\Html;
use common\models\Item;

/* @var $this yii\web\View */
/* @var $rootItems Item[] */

$this->title = 'Предметы';
$this->render('_breadcrumbs', ['model' => null]);

?>
<div class="item-index">

    <?= $this->render('_searchForm', ['query' => '']) ?>
    <h1>Корневые контейнеры</h1>

    <?= $this->render('_items', [
        'items' => $rootItems,
        'isSearch' => false,
    ]) ?>

    <p><?= Html::a('<i class="glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Добавить контейнер', ['items/create', 'isContainer' => 1], ['class' => 'btn btn-success']) ?></p>


</div>
