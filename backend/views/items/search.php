<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var Item[] $items null -- если поиск не выполнялся, [] -- если ничего не найдено */
/** @var array $paths */
/** @var ?string $query */
/** @var ?string $itemId */
/** @var bool $searchInside */
/** @var ?int $containerId */
/** @var ?Item $container */
/** @var bool $isMoreThan */
/** @var Repo $repo */

$this->title = 'Поиск';
$this->render('/_breadcrumbs', ['item' => $container, 'repo' => $repo, 'suffix' => [$this->title]]);
if ($query !== null && is_array($items)) {
    $this->title .= ' «' . $query . '»'; // чтоб в хлебных крошках запрос не отображался
}
?>
<div class="item-search">

    <div id="searchFormGroup">
        <div id="searchFormWrapper">
            <?= $this->render('_searchForm', [
                'query' => $query,
                'containerSearch' => false,
                'showExtraOptions' => (bool) $containerId,
                'searchInside' => $searchInside,
                'containerId' => $containerId,
                'repo' => $repo,
            ]) ?>
        </div>

        <div id="idFormWrapper">
            <?= $this->render('_idForm', [
                'itemId' => $itemId,
                'item' => null,
                'prevItem' => null,
                'nextItem' => null,
                'repo' => $repo,
            ]) ?>
        </div>
    </div>

    <?php
    if ($containerId && $container) { ?>
        <p>Поиск внутри контейнера <a href="<?= Html::encode(Url::to(['items/view', 'repoId' => $repo->id, 'itemId' => $container->itemId])) ?>"><?= Html::encode($container->name); ?></a> <sup style="color: #ccc">#<?= $container->id ?></sup>,
            но можно <a href="<?= Html::encode(Url::to(['items/search', 'repoId' => $repo->id, 'q' => $query])) ?>">поискать везде</a>.
        </p>
    <?php } ?>

    <?php if ($items !== null) { ?>
    <h3>Результаты поиска</h3>

    <?php if (!empty($items)) { ?>
    <p>Всего найдено предметов: <?= count($items) ?><?= $isMoreThan ? ' (часть результатов не отображается)' : '' ?></p>
    <?= $this->render('_items', [
        'items' => $items,
        'paths' => $paths,
        'showPath' => true,
        'showChildren' => true,
        'containerId' => $containerId,
        'repo' => $repo,
    ]) ?>
    <?php } else { ?>
        <p>Ничего не нашлось.</p>
    <?php } ?>

    <?php } ?>

</div>
