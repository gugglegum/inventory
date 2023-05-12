<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var $containers \common\models\Item[] */
/** @var $isSearch boolean */

$this->registerCssFile('@web/css/items.css', [], 'items');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<?php if (!empty($containers)) { ?>
    <?php if ($isSearch) { ?>
        <p>Всего найдено контейнеров: <?= count($containers) ?></p>
    <?php } ?>
    <table class="container-items">
        <?php foreach ($containers as $item) { ?>
            <tr>
                <td class="thumbnail" style="height: 52px; width: 52px">
                    <?php
                    $primaryPhoto = $item->primaryPhoto;
                    if ($primaryPhoto) {
                        echo Html::beginTag('a', ['href' => $item->primaryPhoto->getUrl(), 'rel' => 'item-photos#' . $item->id, 'class' => 'fancybox']);
                    }

                    echo Html::img($item->primaryPhoto
                        ? $item->primaryPhoto->getThumbnailUrl(48, 48, true, true, 90)
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
                                    'url' => ['items/pick-container', 'id' => $tmpItem->id],
                                ];
                                $tmpItem = $tmpItem->parent;
                            }
                            for ($i = count($path) - 1; $i > 0; $i--) {
                                echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                                echo Html::encode($path[$i]['label']);
                                echo Html::endTag('a');
                                echo '<sup style="color: #ccc; font-size: 80%;">#' . Html::encode($path[$i]['id']) . '</sup>';
                                if ($i > 1) {
                                    echo ' &rarr;&nbsp;';
                                }
                            }
                            ?>
                        </div>
                    <?php } ?>

                    <div class="name">
                        <?= Html::beginTag('a', ['href' => Url::to(['items/pick-container', 'id' => $item->id])])
                        . Html::encode($item->name)
                        . Html::endTag('a') ?>&nbsp;<sup style="color: #ccc; font-size: 60%;">#<?= Html::encode($item->id) ?></sup>
                        <?= Html::a('', Url::to(['items/view', 'id' => $item->id]), ['class' => 'glyphicon glyphicon-new-window view-link', 'style' => 'margin-left: 5px', 'target' => '_parent']) ?>
                    </div>

                </td>
            </tr>
        <?php } ?>
    </table>
<?php } else { ?>
    <p><?= $isSearch ? 'Ничего не нашлось. Начать <a href="' . Html::encode(Url::to(['items/pick-container'])) . '">выбирать с корня</a>' : 'Нет вложенных контейнеров' ?>.</p>
<?php } ?>
