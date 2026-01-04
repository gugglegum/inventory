<?php

// Используется в модельном окне выбора нового родительского контейнера

use common\models\Item;
use common\models\Repo;

/** @var \yii\web\View $this */
/** @var Item $parentContainer */
/** @var Item[] $containers */
/** @var string $query */
/** @var Repo $repo */

$this->title = 'Поиск контейнера';
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);
$this->params['breadcrumbs'][] = $this->title;

// Disable debug console in the bottom right corner
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
?>
<div class="pick-container">
    <?= $this->render('_searchForm', [
        'query' => $query,
        'containerSearch' => true,
        'showExtraOptions' => false,
        'searchInside' => false,
        'containerId' => null,
        'repo' => $repo,
    ]) ?>

    <?php if ($query !== '') { ?>
        <h3>Результаты поиска</h3>

        <?= $this->render('_containers', [
            'containers' => $containers,
            'isSearch' => true,
            'repo' => $repo,
        ]) ?>
    <?php } ?>

</div>
