<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Item */
/* @var $parent common\models\Item */
/* @var $children common\models\Item[] */

$this->title = $model->name;

$this->render('_breadcrumbs', ['model' => $model]);
unset($this->params['breadcrumbs'][count($this->params['breadcrumbs']) - 1]['url']);

$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');
$this->registerCssFile('@web/css/item-view.css', ['appendTimestamp' => true], 'item-view');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

?>
<div class="item-view">

    <?= $this->render('_searchForm', ['query' => '']) ?>

    <h1><?= Html::encode($this->title) ?>&nbsp;<sup style="color: #ccc">#<?= Html::encode($model->id) ?></sup></h1>

    <div style="float: left; margin-right: 2em; min-width: 270px; max-width: 35%;">
        <p style="float: right">
            <?= Html::a('<i class="glyphicon glyphicon-edit" style="margin-right: 5px;"></i> Изменить', ['update', 'id' => $model->id]/*, ['class' => 'btn btn-link']*/) ?>
        </p>
        <dl>
            <dt>Описание</dt>
            <dd><?= trim($model->description) !== '' ? nl2br(Html::encode($model->description)) : '<em>Нет описания</em>' ?></dd>

            <dt>Контейнер?</dt>
            <dd><em><?= $model->isContainer ? 'Да' : 'Нет' ?></em></dd>

            <dt>Метки</dt>
            <dd><?php

                $i = 0;
                foreach ($model->fetchTags() as $tag) {
                    if ($i > 0) {
                        echo ', ';
                    }
                    echo Html::a($tag, Url::to(['items/search', 'q' => $tag]));
                    $i++;
                }

                ?></dd>

            <dt>Фотографии</dt>
            <dd>
                <?php
                    $photos = $model->itemPhotos;
                    if (count($photos) !== 0) {
                        echo Html::beginTag('div', ['class' => 'uploaded-photos']);
                        foreach ($photos as $itemPhoto) {
                            echo Html::beginTag('div', ['class' => 'photo-wrapper']);
                            echo Html::beginTag('a', ['href' => $itemPhoto->getUrl(), 'rel' => 'item-photos', 'class' => 'fancybox']);
                            echo Html::img($itemPhoto->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Photo']);
                            echo Html::endTag('a');
                            echo Html::endTag('div');
                        }
                        echo '<div class="clearfix"></div>';
                        echo Html::endTag('div');
                    }
                ?>
            </dd>
        </dl>
    </div>

    <?php if ($model->isContainer || count($children) > 0) { ?>
    <div style="float: left; max-width: 60%; padding-left: 2em; border-left: 1px solid #eee; border-top: 1px solid #eee;">
        <h2>Предметы в этом контейнере</h2>

        <?= $this->render('_items', [
            'items' => $children,
            'isSearch' => false,
        ]) ?>

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
