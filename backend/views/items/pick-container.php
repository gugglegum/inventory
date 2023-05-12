<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Item;

/* @var $this yii\web\View */
/** @var $parentContainerId int */
/* @var $parentContainer Item|null */
/* @var $containers Item[] */
/* @var $query string */

$this->registerJsFile('@web/js/pick-container.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$this->title = 'Выбор контейнера';
$this->render('_breadcrumbs', ['model' => null]);
$this->params['breadcrumbs'][] = $this->title;

// Disable debug console in the bottom right corner
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
?>
<div class="pick-container">
    <?= $this->render('_searchContainerForm', ['query' => '']) ?>

    <?php if ($parentContainerId) { ?>
        <?php if ($parentContainer) { ?>

    <table class="container-items">
        <tr>
            <td class="thumbnail">
                <?php
                $primaryPhoto = $parentContainer->primaryPhoto;
                if ($primaryPhoto) {
                    echo Html::beginTag('a', ['href' => $parentContainer->primaryPhoto->getUrl(), 'rel' => 'item-photos#' . $parentContainer->id, 'class' => 'fancybox']);
                }

                echo Html::img($parentContainer->primaryPhoto
                    ? $parentContainer->primaryPhoto->getThumbnailUrl(100, 100, true, true, 90)
                    : Url::to('@web/images/no-fees-icon-B.png'), ['alt' => 'PHOTO']);

                if ($primaryPhoto) {
                    echo Html::endTag('a');
                }

                ?>
            </td>
            <td class="details">
                <div class="path">
                    <?php
                    echo Html::beginTag('a', ['href' => Url::to(['items/pick-container'])]);
                    echo Html::encode('Предметы');
                    echo Html::endTag('a');

                    $path = [];
                    $tmpItem = $parentContainer->parent;
                    while ($tmpItem) {
                        $path[] = [
                            'id' => $tmpItem->id,
                            'label' => $tmpItem->name,
                            'url' => ['items/pick-container', 'id' => $tmpItem->id],
                        ];
                        $tmpItem = $tmpItem->parent;
                    }
                    if (count($path) > 0) {
                        echo ' &rarr;&nbsp;';
                    }
                    for ($i = count($path) - 1; $i >= 0; $i--) {
                        echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                        echo Html::encode($path[$i]['label']);
                        echo Html::endTag('a');
                        echo '<sup style="color: #ccc; font-size: 80%;">#' . Html::encode($path[$i]['id']) . '</sup>';
                        if ($i > 0) {
                            echo ' &rarr;&nbsp;';
                        }
                    }
                    ?>
                </div>

                <div class="name">
                    <?= Html::encode($parentContainer->name) ?>&nbsp;<sup style="color: #ccc; font-size: 60%;">#<?= Html::encode($parentContainer->id) ?></sup>
                    <?= Html::a('', Url::to(['items/view', 'id' => $parentContainer->id]), ['class' => 'glyphicon glyphicon-new-window view-link', 'style' => 'margin-left: 5px', 'target' => '_parent']) ?>
                </div>

                <button type="button" id="btnPick" class="btn btn-primary" data-toggle="modal" data-container-id="<?= $parentContainer->id ?>">
                    Выбрать
                </button>

            </td>
        </tr>
    </table>
        <?php } else { ?>
            <p>Контейнер #<?= Html::encode($parentContainerId) ?> не найден. Начать <a href="<?= Html::encode(Url::to(['items/pick-container'])) ?>">выбирать с корня</a>.</p>
        <?php } ?>
    <?php } else { ?>
    <h3>Корневые контейнеры</h3>
    <?php } ?>

    <?php if (($parentContainerId && $parentContainer) || (!$parentContainerId)) {
        echo $this->render('_containers', [
            'containers' => $containers,
            'isSearch' => false,
        ]); } ?>

</div>
