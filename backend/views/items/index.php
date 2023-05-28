<?php

use yii\helpers\Html;
use common\models\Item;

/* @var $this yii\web\View */
/* @var $rootItems Item[] */

$this->title = 'Предметы';
$this->render('_breadcrumbs', ['model' => null]);

?>
<div class="item-index">

    <?= $this->render('_searchForm', [
        'query' => '',
        'containerSearch' => false,
        'showExtraOptions' => false,
        'searchInside' => false,
        'containerId' => null,
    ]) ?>
    <h1>Корневые контейнеры</h1>

    <?php if (!empty($rootItems)) { ?>
    <?= $this->render('_items', [
        'items' => $rootItems,
        'showPath' => false,
        'showChildren' => true,
        'containerId' => null,
    ]) ?>
    <?php } else { ?>
        <p>Здесь пока ничего нет.</p>
    <?php } ?>

    <p><?= Html::a('<i class="glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Добавить контейнер', ['items/create', 'isContainer' => 1], ['class' => 'btn btn-success']) ?></p>


</div>
