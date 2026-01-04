<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Item;

/** @var \yii\web\View $this */
/** @var int $parentContainerItemId */
/** @var Item|null $parentContainer */
/** @var Item[] $containers */
/** @var string $query */
/** @var Repo $repo */

$this->registerJsFile('@web/js/pick-container.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'pick-container');

$this->title = 'Выбор контейнера';
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo]);
$this->params['breadcrumbs'][] = $this->title;

// Disable debug console in the bottom right corner
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
?>
<div class="pick-container">
    <?= $this->render('_searchForm', [
        'query' => '',
        'containerSearch' => true,
        'showExtraOptions' => false,
        'searchInside' => false,
        'containerId' => null,
        'repo' => $repo,
    ]) ?>

    <?php if ($parentContainerItemId) { ?>
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
                    echo Html::beginTag('a', ['href' => Url::to(['items/pick-container', 'repoId' => $repo->id, 'id' => 0])]);
                    echo Html::encode('Предметы');
                    echo Html::endTag('a');

                    $path = [];
                    $tmpItem = $parentContainer->parentItem;
                    while ($tmpItem) {
                        $path[] = [
                            'id' => $tmpItem->id,
                            'label' => $tmpItem->name,
                            'url' => ['items/pick-container', 'repoId' => $tmpItem->repoId, 'id' => $tmpItem->itemId],
                        ];
                        $tmpItem = $tmpItem->parentItem;
                    }
                    if (count($path) > 0) {
                        echo ' &rarr;&nbsp;';
                    }
                    for ($i = count($path) - 1; $i >= 0; $i--) {
                        echo Html::beginTag('a', ['href' => Url::to($path[$i]['url'])]);
                        echo Html::encode($path[$i]['label']);
                        echo Html::endTag('a');
                        echo '<sup style="color: #999; font-size: 80%;">#' . Html::encode($path[$i]['id']) . '</sup>';
                        if ($i > 0) {
                            echo ' &rarr;&nbsp;';
                        }
                    }
                    ?>
                </div>

                <div class="name">
                    <?= Html::encode($parentContainer->name) ?>&nbsp;<sup style="color: #ccc; font-size: 60%;">#<?= Html::encode($parentContainer->id) ?></sup>
                    <?= Html::a('', Url::to(['items/view', 'id' => $parentContainer->itemId]), ['class' => 'glyphicon glyphicon-new-window view-link', 'style' => 'margin-left: 5px', 'target' => '_parent']) ?>
                </div>

                <button type="button" id="btnPick" class="btn btn-primary" data-toggle="modal" data-container-id="<?= $parentContainer->itemId ?>">
                    Выбрать
                </button>

            </td>
        </tr>
    </table>
        <?php } else { ?>
            <p>Контейнер #<?= Html::encode($parentContainerItemId) ?> не найден. Начать <a href="<?= Html::encode(Url::to(['items/pick-container', 'repoId' => $repo->id, 'id' => 0])) ?>">выбирать с корня</a>.</p>
        <?php } ?>
    <?php } else { ?>
    <h3>Корневые контейнеры</h3>
    <?php } ?>

    <?php if (($parentContainerItemId && $parentContainer) || (!$parentContainerItemId)) {
        echo $this->render('_containers', [
            'containers' => $containers,
            'isSearch' => false,
            'repo' => $repo,
        ]); } ?>

</div>
