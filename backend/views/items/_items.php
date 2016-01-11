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
                    ? $item->primaryPhoto->getThumbnailUrl(100, 100, [
                        'crop' => true,
                        'upscale' => true
                    ])
                    : Url::to('@web/images/no-fees-icon-B.png'), ['alt' => 'PHOTO']);

                if ($primaryPhoto) {
                    Html::endTag('a');
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
                        $path[] = ['label' => $tmpItem->name, 'url' => ['items/view', 'id' => $tmpItem->id]];
                        $tmpItem = $tmpItem->parent;
                    }
                    for ($i = count($path) - 1; $i > 0; $i--) {
                        echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                        echo Html::encode($path[$i]['label']);
                        echo Html::endTag('a');
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
                        . Html::endTag('a') ?>&nbsp;<sup style="color: #ccc; font-size: 60%;">#<?= Html::encode($item->id) ?></sup><?=
                        Html::a('', Url::to(['items/update', 'id' => $item->id]), ['class' => 'glyphicon glyphicon-edit edit-link', 'style' => 'margin-left: 5px']) ?>
                </div>

                <?php if (trim($item->description) != '') { ?>
                <div class="description"><?php
                    // Выводим укороченное описание, если оно слишком длинное. Заменяем в нём все избыточные белые
                    // пробелы на обычные пробелы.
                    $maxDescriptionLength = 140;
                    $threshold = 10;
                    $shortDescription = preg_replace('/\s+/u', "\x20", $item->description);
                    if (mb_strlen($shortDescription) > $maxDescriptionLength + $threshold) {
                        $shortDescription = mb_substr($shortDescription, 0, $maxDescriptionLength) . '...';
                    }
                    echo Html::encode($shortDescription);
                ?></div>
                <?php } ?>

                <?php foreach ($item->secondaryPhotos as $photo) { ?>
                    <?= Html::beginTag('a', ['href' => $photo->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']) ?>
                    <?= Html::img($photo->getThumbnailUrl(48, 48, ['crop' => true, 'upscale' => true])) ?>
                    <?= Html::endTag('a') ?>
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
