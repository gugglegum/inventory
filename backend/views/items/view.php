<?php

use s9e\TextFormatter\Bundles\Fatdown as TextFormatter;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\Item $model */
/** @var common\models\Item[] $children */
/** @var int $containerId title */
/** @var ?common\models\Item $prevItem */
/** @var ?common\models\Item $nextItem */

$this->title = $model->name;

$this->render('_breadcrumbs', ['model' => $model]);
unset($this->params['breadcrumbs'][count($this->params['breadcrumbs']) - 1]['url']);

$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');
$this->registerCssFile('@web/css/item-view.css', ['appendTimestamp' => true], 'item-view');
$this->registerJsFile('@web/js/item-view.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'item-view');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

$description = trim($model->description) !== '' ? TextFormatter::render(TextFormatter::parse(preg_replace('/(?<![\r\n])\r\n?(?![\r\n])/u', "\n<br>\n", Html::encode($model->description)))) : '<em>Нет описания</em>';
// Выделяем ссылками упоминания ID предметов вида "#1234"
$description = preg_replace_callback('/(?<=[\s,.;()])(#(\d+))(?=[\s.,;()])/', function(array $matches) { return '<a href="' . Html::encode(Url::to(['items/view', 'id' => $matches[2]])) . '">' . $matches[1] . '</a>'; }, $description);

?>
<div id="item-view">

    <div id="searchFormGroup">
        <div id="searchFormWrapper">
            <?= $this->render('_searchForm', [
                'query' => '',
                'containerSearch' => false,
                'showExtraOptions' => $model->isContainer && count($children) > 0,
                'searchInside' => false,
                'containerId' => $containerId,
            ]) ?>
        </div>

        <div id="idFormWrapper">
            <?= $this->render('_idForm', [
                'id' => (string) $model->id,
                'prevItem' => $prevItem,
                'nextItem' => $nextItem,
            ]) ?>
        </div>
    </div>

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #ccc">#<?= Html::encode($model->id) ?></sup></h1>

    <dl id="item-description">
        <div id="lnkEdit">
            <?= Html::a('<i class="glyphicon glyphicon-edit" style="margin-right: 5px;"></i> Изменить', ['update', 'id' => $model->id]) ?>
        </div>
        <dt>Описание</dt>
        <dd><?= $description ?></dd>
    </dl>

    <div class="columns-container">
        <div id="item-info">
            <dl>
                <dt>Контейнер: </dt>
                <dd><em><?= $model->isContainer ? 'Да' : 'Нет' ?></em></dd>
            </dl>
            <dl>
                <dt>Метки:</dt>
                <dd><?php
                    $tags = $model->fetchTags();
                    if (count($tags) > 0) {
                        $i = 0;
                        foreach ($tags as $tag) {
                            if ($i > 0) {
                                echo ', ';
                            }
                            echo Html::a($tag, Url::to(['items/search', 'q' => $tag]));
                            $i++;
                        }
                    } else {
                        echo '<em>Нет</em>';
                    }
                    ?></dd>
            </dl>
            <dl>
                <dt>Дата создания:</dt>
                <dd><?= Html::encode(date('d.m.Y H:i', $model->created)) ?></dd>
            </dl>
            <dl>
                <dt>Дата изменения:</dt>
                <dd><?= Html::encode(date('d.m.Y H:i', $model->updated)) ?></dd>
            </dl>
            <h3>Фотографии</h3>
            <?php
                $photos = $model->itemPhotos;
                if (count($photos) !== 0) {
                    echo Html::beginTag('div', ['class' => 'uploaded-photos']);
                    foreach ($photos as $itemPhoto) {
                        echo Html::beginTag('div', ['class' => 'photo-wrapper']);
                        echo Html::beginTag('div', ['class' => 'photo-frame']);
                        echo Html::beginTag('a', ['href' => $itemPhoto->getUrl(), 'rel' => 'item-photos', 'class' => 'fancybox']);
                        echo Html::img($itemPhoto->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Photo']);
                        echo Html::endTag('a');
                        echo '<div class="upload-date">' . Html::encode(date('d.m.Y H:i', $itemPhoto->created)) . '</div>';
                        echo Html::endTag('div');
                        echo Html::endTag('div');
                    }
                    echo '<div class="clearfix"></div>';
                    echo Html::endTag('div');
                }
            ?>
        </div>

        <?php if ($model->isContainer || count($children) > 0) { ?>
        <div id="item-children">

            <div class="dropdown" style="float:right">
                <span class="dropdown-toggle" type="button" id="dropdownMenu1" data-toggle="dropdown">
                    <div id="optionDots"><i class="glyphicon glyphicon-option-vertical" ></i></div>
                </span>
                <ul class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenu1">
                    <li><a id="menuItemGroupOperation"><span class="glyphicon glyphicon-th-list"></span> Групповые операции</a></li>
                    <li><a id="menuItemCheckAll"><span class="glyphicon glyphicon-check"></span> Выделить все</a></li>
                    <li><a id="menuItemUncheckAll"><span class="glyphicon glyphicon-unchecked"></span> Снять все</a></li>
                </ul>
            </div>


            <h2>Предметы в этом контейнере</h2>

            <?php if (!empty($children)) { ?>
            <?= $this->render('_items', [
                'items' => $children,
                'showPath' => false,
                'showChildren' => true,
                'containerId' => null,
            ]) ?>
                <p>Всего предметов: <?= count($children) ?></p>
            <?php } else { ?>
                <p>Здесь пока ничего нет.</p>
            <?php } ?>

            <p style="margin-top: 1em"><?php
            if ($model->isContainer) {
                echo Html::a('<i class="glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Добавить предмет внутрь', ['items/create', 'parentId' => $model->id], ['class' => 'btn btn-success']);
            }
            ?></p>

            <?= $this->render('_importForm', [
                'parent' => $model,
                'text' => '',
            ]) ?>
        </div>
        <?php } ?>
    </div>

    <div class="clearfix"></div>

    <p style="margin-top: 3em">
        <?= Html::a('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>
</div>
