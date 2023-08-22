<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Item;

/** @var yii\web\View $this */
/** @var Item[] $items */
/** @var array $paths */
/** @var string $query */
/** @var bool $searchInside */
/** @var ?int $containerId */
/** @var bool $isMoreThan */

$this->title = 'Поиск';
$this->render('_breadcrumbs', ['model' => null]);
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-search">

    <h1><?= Html::encode($this->title) ?></h1>

    <div id="searchFormGroup">
        <div id="searchFormWrapper">
            <?= $this->render('_searchForm', [
                'query' => $query,
                'containerSearch' => false,
                'showExtraOptions' => (bool) $containerId,
                'searchInside' => $searchInside,
                'containerId' => $containerId,
            ]) ?>
        </div>

        <div id="idFormWrapper">
            <?= $this->render('_idForm', [
                'id' => '',
                'prevItem' => null,
                'nextItem' => null,
            ]) ?>
        </div>
    </div>

    <?php
    if ($containerId) {
        /** @var Item $container */
        $container = Item::findOne($containerId);
    }
    if ($containerId && $container) { ?>
        <p>Поиск внутри контейнера <a href="<?= Html::encode(Url::to(['items/view', 'id' => $container->id])) ?>"><?= Html::encode($container->name); ?></a> <sup style="color: #ccc">#<?= $container->id ?></sup>,
            но можно <a href="<?= Html::encode(Url::to(['items/search', 'q' => $query])) ?>">поискать везде</a>.
        </p>
    <?php } ?>

    <?php if ($query !== '') { ?>
    <h3>Результаты поиска</h3>

    <?php if (!empty($items)) { ?>
    <p>Всего найдено предметов: <?= count($items) ?><?= $isMoreThan ? ' (часть результатов не отображается)' : '' ?></p>
    <?= $this->render('_items', [
        'items' => $items,
        'paths' => $paths,
        'showPath' => true,
        'showChildren' => true,
        'containerId' => $containerId,
    ]) ?>
    <?php } else { ?>
        <p>Ничего не нашлось.</p>
    <?php } ?>

    <?php } ?>

</div>
