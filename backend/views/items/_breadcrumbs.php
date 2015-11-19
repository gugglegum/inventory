<?php

/** @var $model common\models\Item */

$this->params['breadcrumbs'][] = ['label' => 'Предметы', 'url' => ['items/index']];
$path = [];
$tmpItem = $model;
while ($tmpItem) {
    $path[] = ['label' => $tmpItem->name, 'url' => ['items/view', 'id' => $tmpItem->id]];
    $tmpItem = $tmpItem->parent;
}
for ($i = count($path) - 1; $i >= 0; $i--) {
    $this->params['breadcrumbs'][] = $path[$i];
}
