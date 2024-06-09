<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $id */
/** @var ?common\models\Item $item */
/** @var ?common\models\Item $prevItem */
/** @var ?common\models\Item $nextItem */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

$tabIndex = 4;

?>
<form action="<?= Html::encode(Url::to(['items/view'])) ?>" id="idForm">
    <?php if ($item) { ?><a href="<?= Html::encode(Url::to($item->parentId !== null ? ['items/view', 'id' => $item->parentId] : ['items/index'])) ?>" tabindex="<?= $tabIndex++ ?>"><span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span></a><?php } else { ?><span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span><?php } ?>
    <?php if ($prevItem) { ?><a href="<?= Html::encode(Url::to(['items/view', 'id' => $prevItem->id])) ?>" tabindex="<?= $tabIndex++ ?>"><span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span></a><?php } else { ?><span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span><?php } ?>
    <label for="inputId">#</label>
    <input type="number" pattern="\d*" name="id" id="inputId" value="<?= Html::encode($id) ?>" tabindex="<?= $tabIndex++ ?>">
    <button type="submit" tabindex="<?= $tabIndex++ ?>"><span class="glyphicon glyphicon-triangle-right" aria-hidden="true"></span></button>
    <?php if ($nextItem) { ?><a href="<?= Html::encode(Url::to(['items/view', 'id' => $nextItem->id])) ?>" tabindex="<?= $tabIndex++ ?>"><span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span></a><?php } else { ?><span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span><?php } ?>
</form>
