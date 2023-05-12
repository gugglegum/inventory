<?php

use yii\helpers\Html;
use common\models\Item;

/* @var $this yii\web\View */
/* @var $parentContainer Item */
/* @var $containers Item[] */
/* @var $query string */

$this->title = 'Поиск контейнера';
$this->render('_breadcrumbs', ['model' => null]);
$this->params['breadcrumbs'][] = $this->title;

// Disable debug console in the bottom right corner
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
?>
<div class="pick-container">
    <?= $this->render('_searchContainerForm', ['query' => $query]) ?>

    <?php if ($query !== '') { ?>
        <h3>Результаты поиска</h3>

        <?= $this->render('_containers', [
            'containers' => $containers,
            'isSearch' => true,
        ]) ?>
    <?php } ?>

</div>
