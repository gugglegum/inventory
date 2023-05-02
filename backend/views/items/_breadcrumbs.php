<?php

/** @var $model common\models\Item */

use yii\helpers\Html;

$this->params['breadcrumbs'][] = ['label' => 'Предметы', 'url' => ['items/index']];
$path = [];
if ($model) {
    $tmpItem = clone $model;
    $tmpItem->refresh(); // Предотвращает неправильную цепочку родителей, когда в parentId из POST был загружен какой-то невалидный ID
    while ($tmpItem) {
        $path[] = [
            'label' => $tmpItem->name,
            'url' => ['items/view', 'id' => $tmpItem->id],
            'template' => empty($path)
                ? '<li class="active">{link}<sup style="margin-left: 3px">#' . Html::encode($tmpItem->id) . "</sup></li>\n"
                : '<li>{link}<sup style="margin-left: 3px; color: #777">#' . Html::encode($tmpItem->id) . "</sup></li>\n",
        ];
        $tmpItem = $tmpItem->parent;
    }
    for ($i = count($path) - 1; $i >= 0; $i--) {
        $this->params['breadcrumbs'][] = $path[$i];
    }
}
