<?php

/** @var $model common\models\Item */

use yii\helpers\Html;

$this->params['breadcrumbs'][] = ['label' => 'Предметы', 'url' => ['items/index']];
$path = [];
$tmpItem = $model;
while ($tmpItem) {
    $path[] = [
        'label' => $tmpItem->name,
        'url' => ['items/view', 'id' => $tmpItem->id],
        'template' => $tmpItem->parent
            ? '<li class="active">{link}<sup style="margin-left: 3px">#' . Html::encode($tmpItem->id) . "</sup></li>\n"
            : '<li>{link}<sup style="margin-left: 3px">#' . Html::encode($tmpItem->id) . "</sup></li>\n",
    ];
    $tmpItem = $tmpItem->parent;
}
for ($i = count($path) - 1; $i >= 0; $i--) {
    $this->params['breadcrumbs'][] = $path[$i];
}
