<?php

use backend\models\InventoryItemConfirmForm;
use backend\models\InventoryItemUnconfirmForm;
use common\models\Inventory;
use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\ActiveForm;

/** @var \yii\web\View $this */
/** @var \common\models\Inventory $inventory */
/** @var Item $container */
/** @var Item[] $notConfirmedItems */
/** @var Item[] $confirmedItems */
/** @var array[] $paths */
/** @var Repo $repo */
/** @var InventoryItemConfirmForm $inventoryItemConfirm */
/** @var InventoryItemUnconfirmForm $inventoryItemUnconfirm */

$this->title = 'Инвентаризация ' . $inventory->id;

$this->render('/_breadcrumbs', ['item' => $container, 'repo' => $repo, 'suffix' => [
    ['url' => Url::to(['inventory/index', 'repoId' => $repo->id, 'itemId' => $container->itemId]), 'label' => 'Инвентаризации'],
    'Инвентаризация ' . $inventory->id]
]);

$this->registerCssFile('@web/css/inventory-view.css', ['appendTimestamp' => true], 'inventory-view');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

$unconfirmedBottomCallback = function (Item $item) use ($repo, $container, $inventory) {
    if ($inventory->status !== Inventory::STATUS_OPENED) {
        return;
    }
    $form = ActiveForm::begin([
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]);
    $inventoryItem = new InventoryItemConfirmForm();
    $inventoryItem->itemId = $item->id;
    echo $form->field($inventoryItem, 'itemId')->hiddenInput()->label(false);
    echo Html::submitButton('<i class="glyphicon glyphicon glyphicon-plus-sign" style="margin-right: 5px;"></i> Подтвердить наличие', [
        'class' => 'btn btn-primary',
    ]);
    ActiveForm::end();
};
$confirmedBottomCallback = function (Item $item) use ($repo, $container, $inventory) {
    if ($inventory->status !== Inventory::STATUS_OPENED) {
        return;
    }
    $form = ActiveForm::begin([
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]);
    $inventoryItem = new InventoryItemUnconfirmForm();
    $inventoryItem->itemId = $item->id;
    echo $form->field($inventoryItem, 'itemId')->hiddenInput()->label(false);
    echo Html::submitButton('<i class="glyphicon glyphicon glyphicon-minus-sign" style="margin-right: 5px;"></i> Снять подтверждение', [
        'class' => 'btn btn-danger',
    ]);
    ActiveForm::end();
};

$tabIndex = 1;
?>
<div id="inventory-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <dl class="params">
        <dt>Контейнер:</dt>
        <dd><a href="<?= Html::encode(Url::to(['items/view', 'repoId' => $repo->id, 'id' => $container->itemId])) ?>"><?= Html::encode($container->name) ?></a></dd>
    </dl>
    <dl class="params">
        <dt>Период инвентаризации:</dt>
        <dd><?= Html::encode(date('d.m.Y H:i T', $inventory->created)) ?> &mdash;
            <?php if ($inventory->status === Inventory::STATUS_CLOSED) { ?>
            <?= Html::encode(date('d.m.Y H:i T', $inventory->closed)) ?>
            <?php } else { ?>
            текущий момент
            <?php } ?>
        </dd>
    </dl>
    <dl class="params">
        <dt>Статус:</dt>
        <dd><?= Html::encode($inventory->status === Inventory::STATUS_OPENED ? 'Открыта' : 'Закрыта') ?></dd>
    </dl>

    <?php if ($inventory->status === Inventory::STATUS_CLOSED) { ?>
        <div class="alert alert-info" role="alert" style="margin: 2em 0; max-width: 50em; font-size: 130%">Это завершенная инвентаризация. Здесь уже ничего нельзя делать &mdash; она просто для истории.</div>
    <?php } ?>

    <div class="columns-container">
        <div class="column">
            <h2>Неподтвержденные предметы</h2>

            <?php if ($inventory->status === Inventory::STATUS_OPENED) { ?>
                <?php
                $form = ActiveForm::begin([
                    'method' => 'post',
                    'options' => ['class' => 'inventory-by-item-id form-inline'],
                    'fieldConfig' => [
                        'options' => ['class' => 'form-group', 'style' => 'margin-right: 10px;'],
                        'labelOptions' => ['style' => 'margin-right: 6px;'],
                    ],
                    'validateOnBlur' => false,
                ]);
                echo $form->field($inventoryItemConfirm, 'itemId', [
                    'inputTemplate' =>
                        '<div class="input-group" style="width:auto;">' .
                        '<span class="input-group-addon">' . Html::encode($repo->id) . '#</span>' .
                        '{input}' .
                        '</div>',
                    ])
                    ->label('ID предмета:')
                    ->textInput([
                        'style' => 'width:100px;',
                        'autocomplete' => 'off',
                    ]);

                echo Html::submitButton(
                    '<i class="glyphicon glyphicon-plus-sign" style="margin-right:5px;"></i> Подтвердить наличие',
                    ['class' => 'btn btn-primary', 'encode' => false, 'style' => 'vertical-align: top;']
                );
                ActiveForm::end();
                ?>
            <?php } ?>

            <?php if (!empty($notConfirmedItems)) { ?>
                <?= $this->render('/items/_items', [
                        'items' => $notConfirmedItems,
                        'showPath' => true,
                        'showChildren' => false,
                        'containerId' => null,
                        'paths' => $paths,
                        'repo' => $repo,
                        'bottomCallback' => $unconfirmedBottomCallback,
                ]) ?>
                <p>Всего неподтверждённых предметов: <?= count($notConfirmedItems) ?></p>
            <?php } else { ?>
                <p><?php if ($container->getItems()->exists()) { ?>Наличие всех предметов подтверждено!<?php } else { ?>В этом контейнере нет предметов.<?php } ?></p>
            <?php } ?>
        </div>

        <div class="column">
            <h2>Подтвержденные предметы</h2>

            <?php if ($inventory->status === Inventory::STATUS_OPENED && count($confirmedItems) > 0) { ?>

                <?php
                $form = ActiveForm::begin([
                    'method' => 'post',
                    'options' => ['class' => 'inventory-by-item-id form-inline'],
                    'fieldConfig' => [
                        'options' => ['class' => 'form-group', 'style' => 'margin-right: 10px;'],
                        'labelOptions' => ['style' => 'margin-right: 6px;'],
                    ],
                    'validateOnBlur' => false,
                ]);
                echo $form->field($inventoryItemUnconfirm, 'itemId', [
                    'inputTemplate' =>
                        '<div class="input-group" style="width:auto;">' .
                        '<span class="input-group-addon">' . Html::encode($repo->id) . '#</span>' .
                        '{input}' .
                        '</div>',
                    ])
                    ->label('ID предмета:')
                    ->textInput([
                        'style' => 'width:100px;',
                        'autocomplete' => 'off',
                    ]);

                echo Html::submitButton(
                    '<i class="glyphicon glyphicon-minus-sign" style="margin-right:5px;"></i> Снять подтверждение',
                    ['class' => 'btn btn-danger', 'encode' => false, 'style' => 'vertical-align: top;']
                );
                ActiveForm::end();
                ?>
            <?php } ?>

            <?php if (!empty($confirmedItems)) { ?>
                <?= $this->render('/items/_items', [
                        'items' => $confirmedItems,
                        'showPath' => true,
                        'showChildren' => false,
                        'containerId' => null,
                        'paths' => $paths,
                        'repo' => $repo,
                        'bottomCallback' => $confirmedBottomCallback,
                ]) ?>
                <p>Всего подтверждённых предметов: <?= count($confirmedItems) ?></p>
            <?php } else { ?>
                <p>Ещё нет ни одного подтверждённого предмета.</p>
            <?php } ?>
        </div>
    </div>

    <?php if ($inventory->status === Inventory::STATUS_OPENED) { ?>
    <h2>Завершение инвентаризации</h2>

    <p>Подтвержденные предметы получат отметку о подтверждении наличия, а неподтвержденные предметы напротив получат отметку об отсутствии.</p>

    <?php
        $itemsToBeMoved = array_filter($confirmedItems, function(Item $item) use ($container) {
            return $item->parentItemId !== $container->itemId;
        });

        if (count($itemsToBeMoved) > 0) { ?>
            <p><strong>Внимание!</strong> В ходе инвентаризации в этом контейнере (<a href="<?= Html::encode(Url::to(['items/view', 'repoId' => $repo->id, 'id' => $container->itemId])) ?>"><?= Html::encode($container->name) ?></a>) были подтверждены предметы из других контейнеров. При завершении инвентаризации следующие предметы будут перемещены в этот контейнер:</p>
            <?= $this->render('/items/_items', [
                'items' => $itemsToBeMoved,
                'showPath' => true,
                'showChildren' => false,
                'containerId' => null,
                'paths' => $paths,
                'repo' => $repo,
            ]) ?>
    <?php } ?>

    <div class="bottom-buttons">
        <?php
            // Форма на закрытие инвентаризации
            $form = ActiveForm::begin([
                    'action' => Url::to(['close', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]),
                    'method' => 'post',
                    'options' => [
                    ],
            ]);
            echo Html::submitButton('<i class="glyphicon glyphicon glyphicon-check" style="margin-right: 5px;"></i> Завершить инвентаризацию', [
                    'class' => 'btn btn-success',
            ]);
            ActiveForm::end();

            // Форма отмены (по сути удаления) инвентаризации
            $form = ActiveForm::begin([
                    'action' => Url::to(['delete', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]),
                    'method' => 'post',
                    'options' => [
                    ],
            ]);
            echo Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Отмена', [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => 'Вы действительно хотите отменить эту инвентаризацию?',
                    ],
            ]);
            ActiveForm::end();
        ?>
    </div>
    <?php } else { ?>

    <div style="margin-top: 3em">

        <?php $form = ActiveForm::begin([
            'action' => Url::to(['delete', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id]),
            'method' => 'post',
            'options' => [
                'style' => 'margin-top: 1em',
            ],
        ]); ?>

        <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Вы действительно хотите удалить эту инвентаризацию?',
                'method' => 'post',
            ],
        ]) ?>
        <?php ActiveForm::end(); ?>
    </div>
    <?php } ?>
</div>
