<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var $items \common\models\Item[] */
/** @var $isSearch boolean */

$this->registerCssFile('@web/css/items.css', [], 'items');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<?php if (!empty($items)) { ?>
<?php if ($isSearch) { ?>
<p>Всего найдено предметов: <?= count($items) ?></p>
<?php } ?>
<table class="container-items">
    <?php foreach ($items as $item) { ?>
        <tr>
            <td class="thumbnail">
                <?php
                $primaryPhoto = $item->primaryPhoto;
                if ($primaryPhoto) {
                    echo Html::beginTag('a', ['href' => $item->primaryPhoto->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']);
                }

                echo Html::img($item->primaryPhoto
                    ? $item->primaryPhoto->getThumbnailUrl(100, 100, true, true, 90)
                    : Url::to('@web/images/no-fees-icon-B.png'), ['alt' => 'PHOTO']);

                if ($primaryPhoto) {
                    echo Html::endTag('a');
                }

                ?>
            </td>
            <td class="details">
                <?php if ($isSearch) { ?>
                <div class="path">
                    <?php
                    $path = [];
                    $tmpItem = $item;
                    while ($tmpItem) {
                        $path[] = [
                            'id' => $tmpItem->id,
                            'label' => $tmpItem->name,
                            'url' => ['items/view', 'id' => $tmpItem->id],
                        ];
                        $tmpItem = $tmpItem->parent;
                    }
                    for ($i = count($path) - 1; $i > 0; $i--) {
                        echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                        echo Html::encode($path[$i]['label']);
                        echo Html::endTag('a');
                        echo '<sup>#' . Html::encode($path[$i]['id']) . '</sup>';
                        if ($i > 1) {
                            echo ' &rarr;&nbsp;';
                        }
                    }
                    ?>
                </div>
                <?php } ?>

                <div class="name">
                    <?= Html::beginTag('a', ['href' => Url::to(['items/view', 'id' => $item->id])])
                        . Html::encode($item->name)
                        . Html::endTag('a') ?>&nbsp;<sup>#<?= Html::encode($item->id) ?></sup><?=
                        Html::a('', Url::to(['items/update', 'id' => $item->id]), ['class' => 'glyphicon glyphicon-edit edit-link', 'style' => 'margin-left: 5px']) ?>
                </div>

                <?php $secondaryPhotos = $item->secondaryPhotos; if (count($secondaryPhotos) != 0) { ?>
                <div class="secondary-photos">
                <?php foreach ($item->secondaryPhotos as $photo) { ?>
                    <?= Html::beginTag('a', ['href' => $photo->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']) ?>
                    <?= Html::img($photo->getThumbnailUrl(48, 48, true, true, 90), ['alt' => 'Photo']) ?>
                    <?= Html::endTag('a') ?>
                <?php } ?>
                </div>
                <?php } ?>

                <?php if (($description = trim($item->description)) != '') { ?>
                <div class="description"><?php
                    // Выводим укороченное описание, если оно слишком длинное. Заменяем в нём все избыточные белые
                    // пробелы на обычные пробелы.
                    $maxDescriptionLength = 140;
                    $threshold = 10;
                    $description = preg_replace('/\s+/u', "\x20", $description);
                    if (mb_strlen($description) > $maxDescriptionLength + $threshold) {
                        $description = rtrim(mb_substr($description, 0, $maxDescriptionLength)) . '...';
                    }
                    echo Html::encode($description);
                ?></div>
                <?php } ?>

                <?php $tags = $item->itemTags; if (count($tags) != 0) { ?>
                <div class="tags">Метки: <?php
                    $first = true;
                    foreach ($tags as $tag) {
                        if (!$first) {
                            echo ', ';
                        }
                        echo Html::beginTag('a', ['href' => Url::to(['items/search', 'q' => $tag->tag])])
                            . Html::encode($tag->tag)
                            . Html::endTag('a');
                        $first = false;
                    }
                ?></div>
                <?php } ?>

                <div class="child-items">
                <?php $i = 0; foreach ($item->items as $childItem) {
                    if ($i > 0) {
                        echo ', ';
                    }
                    echo Html::beginTag('a', ['href' => Url::to(['items/view', 'id' => $childItem->id])]);
                    echo Html::encode($childItem->name);
                    echo Html::endTag('a');
                    $i++;
                } ?>
                </div>
            </td>
        </tr>
    <?php } ?>
</table>
<?php if (!$isSearch) { ?>
<p>Всего предметов: <?= count($items) ?></p>
<?php } ?>
<?php } else { ?>
<p><?= $isSearch ? 'Ничего не нашлось' : 'Здесь пока ничего нет' ?>.</p>
<?php } ?>
