<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;

/** @var Item $item */
/** @var ?Repo $repo */
/** @var ?array $suffix */

$this->params['breadcrumbs'][] = ['label' => 'Репозитории', 'url' => ['repo/index']];
if ($repo) {
    $this->params['breadcrumbs'][] = ['label' => $repo->name, 'url' => ['items/index', 'repoId' => $repo->id]];
    $path = [];
    if ($item) {
        $tmpItem = clone $item;
        $tmpItem->refresh(); // Предотвращает неправильную цепочку родителей, когда в parentItemId из POST был загружен какой-то невалидный ID
        while ($tmpItem) {
            $path[] = [
                'label' => $tmpItem->name,
                'url' => ['items/view', 'repoId' => $repo->id, 'id' => $tmpItem->itemId],
                'template' => empty($path)
                    ? '<li class="active">{link}<sup style="margin-left: 3px">#' . Html::encode($tmpItem->itemId) . "</sup></li>\n"
                    : '<li>{link}<sup style="margin-left: 3px; color: #777">#' . Html::encode($tmpItem->itemId) . "</sup></li>\n",
            ];
            $tmpItem = $tmpItem->parentItem;
        }
        for ($i = count($path) - 1; $i >= 0; $i--) {
            $this->params['breadcrumbs'][] = $path[$i];
        }
    }
}
if (!empty($suffix)) {
    $this->params['breadcrumbs'] = array_merge($this->params['breadcrumbs'], $suffix);
}
if (is_array($this->params['breadcrumbs'][count($this->params['breadcrumbs']) - 1])) {
    unset($this->params['breadcrumbs'][count($this->params['breadcrumbs']) - 1]['url']);
}
