<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Item[] $items */
/** @var array $paths (optional) */
/** @var bool $showPath  */
/** @var bool $showChildren */
/** @var ?int $containerId */
/** @var Repo $repo */
/** @var ?callable $bottomCallback */

$this->registerCssFile('@web/css/items.css', ['appendTimestamp' => true], 'items');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<table class="container-items">
    <?php foreach ($items as $item) { ?>
        <tr id="<?= 'item' . $item->repoId . '-' . $item->itemId ?>">
            <td class="thumbnail">
                <?php
                $primaryPhoto = $item->primaryPhoto;
                if ($primaryPhoto) {
                    echo Html::beginTag('a', ['href' => $item->primaryPhoto->photo->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']);
                }

                echo Html::img($item->primaryPhoto
                    ? $item->primaryPhoto->photo->getThumbnailUrl(100, 100, true, true, 90)
                    : Url::to('@web/images/no-fees-icon-B.png'), ['alt' => 'PHOTO']);

                if ($primaryPhoto) {
                    echo Html::endTag('a');
                }

                ?>
            </td>
            <td class="details">
                <?php if ($showPath && array_key_exists($item->id, $paths)) { ?>
                <div class="path">
                    <?php
                    $path = $paths[$item->id];
                    for ($i = count($path) - 1; $i > 0; $i--) {
                        echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                        echo Html::encode($path[$i]['label']);
                        echo Html::endTag('a');
                        echo ' <sup>' . Html::encode($path[$i]['repoId']) . '#' . Html::encode($path[$i]['itemId']) . '</sup>';
                        if ($i > 1) {
                            echo ' &rarr;&nbsp;';
                        }
                    }
                    ?>
                </div>
                <?php } ?>

                <div class="name">
                    <?= Html::beginTag('a', ['href' => Url::to(['items/view', 'repoId' => $item->repoId, 'itemId' => $item->itemId])])
                        . Html::encode($item->name)
                        . Html::endTag('a') ?>&nbsp;<sup><?= Html::encode($item->repoId) ?>#<?= Html::encode($item->itemId) ?></sup><?=
                        Html::a('', Url::to(['items/update', 'repoId' => $item->repoId, 'itemId' => $item->itemId]), ['class' => 'glyphicon glyphicon-edit edit-link', 'style' => 'margin-left: 5px']) ?>
                </div>

                <?php $secondaryPhotos = $item->secondaryPhotos; if (count($secondaryPhotos) != 0) { ?>
                <div class="secondary-photos">
                <?php foreach ($item->secondaryPhotos as $itemPhoto) { ?>
                    <?= Html::beginTag('a', ['href' => $itemPhoto->photo->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']) ?>
                    <?= Html::img($itemPhoto->photo->getThumbnailUrl(48, 48, true, true, 90), ['alt' => 'Photo']) ?>
                    <?= Html::endTag('a') ?>
                <?php } ?>
                </div>
                <?php } ?>

                <?php if (($description = trim((string) $item->description)) != '') { ?>
                <div class="description"><?php
                    // Выводим укороченное описание, если оно слишком длинное. Заменяем в нём все избыточные белые
                    // пробелы на обычные пробелы.
                    $maxDescriptionLength = 250;
                    $threshold = 10;
                    $description = preg_replace('/\s+/u', "\x20", $description);
                    if (mb_strlen($description) > $maxDescriptionLength + $threshold) {
                        $description = rtrim(mb_substr($description, 0, $maxDescriptionLength)) . '...';
                    }
                    echo \common\helpers\MarkdownFormatter::format($description, $repo);
                ?></div>
                <?php } ?>

                <?php $tags = $item->itemTags; if (count($tags) != 0) { ?>
                <div class="tags">Метки: <?php
                    $first = true;
                    foreach ($tags as $tag) {
                        if (!$first) {
                            echo ', ';
                        }
                        echo Html::beginTag('a', ['href' => Url::to(['items/search', 'repoId' => $item->repoId, 'q' => $tag->tag])])
                            . Html::encode($tag->tag)
                            . Html::endTag('a');
                        $first = false;
                    }
                ?></div>
                <?php } ?>

                <?php if ($showChildren) { ?>
                <div class="child-items">
                <?php
                    $i = 0;
                    /** @var Item $childItem */
                    foreach ($item->getItems()->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])->all() as $childItem) {
                        if ($i > 0) {
                            echo ', ';
                        }
                        echo Html::beginTag('a', ['href' => Url::to(['items/view', 'repoId' => $item->repoId, 'itemId' => $childItem->itemId])]);
                        echo Html::encode($childItem->name);
                        echo Html::endTag('a');
                        $i++;
                    } ?>
                </div>
                <?php } ?>
                <?php if (!empty($bottomCallback)) {
                    $bottomCallback($item);
                } ?>
            </td>
<!--            <td><label><input name="item_check[--><?php //= $item->id ?><!--]" type="checkbox"></label></td>-->
        </tr>
    <?php } ?>
</table>
