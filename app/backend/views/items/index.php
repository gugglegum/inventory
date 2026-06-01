<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var Repo $repo */
/** @var Item[] $rootItems */

$this->title = $repo->name;
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);

?>
<div class="item-index">

    <div id="searchFormGroup">
        <div id="searchFormWrapper">
            <?= $this->render('_searchForm', [
                'query' => '',
                'containerSearch' => false,
                'showExtraOptions' => false,
                'searchInside' => false,
                'containerId' => null,
                'repo' => $repo,
            ]) ?>
        </div>

        <div id="idFormWrapper">
            <?= $this->render('_idForm', [
                'itemId' => '',
                'item' => null,
                'prevItem' => null,
                'nextItem' => null,
                'repo' => $repo,
            ]) ?>
        </div>
    </div>

    <h1><?= Html::encode($repo->name) ?></h1>

<?php if ($repo->description !== null) { ?>
    <div style="margin: 1em 0"><?= \common\helpers\MarkdownFormatter::format($repo->description, $repo) ?></div>
<?php } ?>

    <?php if (!empty($rootItems)) { ?>
    <?= $this->render('_items', [
        'items' => $rootItems,
        'showPath' => false,
        'showChildren' => true,
        'containerId' => null,
        'repo' => $repo,
    ]) ?>
    <?php } else { ?>
        <p>Здесь пока ничего нет.</p>
    <?php } ?>

    <p><?= Html::a('<i class="glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Добавить контейнер', ['items/create', 'repoId' => $repo->id, 'parentItemId' => 0, 'isContainer' => 1], ['class' => 'btn btn-success']) ?></p>


</div>
