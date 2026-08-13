<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var string $itemId */
/** @var ?Item $item */
/** @var ?Item $prevItem */
/** @var ?Item $nextItem */
/** @var Repo $repo */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

$tabIndex = 4;

?>
<form action="<?= Html::encode(Url::to(['items/search', 'repoId' => $repo->id])) ?>" id="idForm">
    <?php if ($item) { ?><a href="<?= Html::encode(Url::to($item->parentItemId !== null ? ['items/view', 'repoId' => $item->repoId, 'itemId' => $item->parentItemId] : ['items/index'])) ?>" tabindex="<?= $tabIndex++ ?>" title="На уровень выше" aria-label="На уровень выше"><span class="bi bi-arrow-up" aria-hidden="true"></span></a><?php } else { ?><span class="bi bi-arrow-up" aria-hidden="true"></span><?php } ?>
    <?php if ($prevItem) { ?><a href="<?= Html::encode(Url::to(['items/view', 'repoId' => $prevItem->repoId, 'itemId' => $prevItem->itemId])) ?>" tabindex="<?= $tabIndex++ ?>" title="Предыдущий предмет" aria-label="Предыдущий предмет"><span class="bi bi-arrow-left" aria-hidden="true"></span></a><?php } else { ?><span class="bi bi-arrow-left" aria-hidden="true"></span><?php } ?>
    <label for="inputId"><?= Html::encode($repo->id) ?>#</label>
    <input type="number" pattern="\d*" name="id" id="inputId" value="<?= Html::encode($itemId) ?>" tabindex="<?= $tabIndex++ ?>">
    <button type="submit" tabindex="<?= $tabIndex++ ?>" title="Перейти к ID" aria-label="Перейти к указанному ID"><span class="bi bi-caret-right-fill" aria-hidden="true"></span></button>
    <?php if ($nextItem) { ?><a href="<?= Html::encode(Url::to(['items/view', 'repoId' => $nextItem->repoId, 'itemId' => $nextItem->itemId])) ?>" tabindex="<?= $tabIndex++ ?>" title="Следующий предмет" aria-label="Следующий предмет"><span class="bi bi-arrow-right" aria-hidden="true"></span></a><?php } else { ?><span class="bi bi-arrow-right" aria-hidden="true"></span><?php } ?>
</form>
<script>
    document.getElementById('inputId').addEventListener('focus', function() {
        this.select();
    });
</script>
