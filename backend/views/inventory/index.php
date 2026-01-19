<?php

use common\models\Inventory;
use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Repo $repo */
/** @var Item $container */
/** @var Inventory[] $inventories */

$this->title = 'Инвентаризации';
$this->render('/_breadcrumbs', ['item' => $container, 'repo' => $repo, 'suffix' => [$this->title]]);

$this->registerCssFile('@web/css/inventories.css', ['appendTimestamp' => true], 'inventories');

?>
<div class="inventory-index">
    <h1><?= Html::encode($this->title . ': ' . $container->name) ?></h1>

    <?php
        if ($container->getLastOpenedInventory() === null) {
            $form = ActiveForm::begin([
                    'action' => Url::to(['create', 'repoId' => $repo->id, 'itemId' => $container->itemId]),
                    'method' => 'post',
                    'options' => [
                            'style' => 'margin: 1.5em 0',
                    ],
            ]);

            echo Html::submitButton('<i class="glyphicon glyphicon glyphicon-check" style="margin-right: 5px;"></i> Начать инвентаризацию', [
                    'class' => 'btn btn-primary',
            ]);
            echo Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['items/view', 'repoId' => $repo->id, 'id' => $container->itemId]), ['style' => 'margin-left: 1em']);
            ActiveForm::end();
        } else {
            ?><p>У вас уже есть начатая инвентаризация, вы можете <a href="<?= Html::encode(Url::to(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $container->lastOpenedInventory->id])) ?>">продолжить её</a>.</p>
            <?php
        }
    ?>

    <?php if (!empty($inventories)) { ?>
    <ul class="inventories">
        <?php foreach ($inventories as $inventory) { ?>
        <li<?= $inventory->status === \common\models\Inventory::STATUS_OPENED ? ' class="active"' : '' ?>>
            <a href="<?= Html::encode(Url::to(['inventory/view', 'repoId' => $repo->id, 'itemId' => $container->itemId, 'inventoryId' => $inventory->id])) ?>">Инвентаризация <?= Html::encode($inventory->id) ?></a>
            (<?= Html::encode(date('d.m.Y H:i T', $inventory->created)) ?> &mdash;
            <?php if ($inventory->status === \common\models\Inventory::STATUS_CLOSED) { ?>
                <?= Html::encode(date('d.m.Y H:i T', $inventory->closed)) ?>
            <?php } else { ?>
                текущий момент
            <?php } ?>)
        </li>
        <?php } ?>
    </ul>
    <?php } else { ?>
        <p>Пока нет ни одной инвентаризации.</p>
    <?php } ?>
</div>
