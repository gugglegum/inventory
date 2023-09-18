<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

use yii\helpers\Html;

$this->title = $name;

// Little hack to display item ID in IdForm on the error page
if (preg_match('|/items/view\?id=([^&]+)|', $_SERVER['REQUEST_URI'], $m)) {
    $itemId = $m[1];
} else {
    $itemId = '';
}
?>
<div class="site-error">

    <h1><?= Html::encode($this->title) ?></h1>

    <div id="searchFormGroup">
        <div id="searchFormWrapper">
            <?= $this->render('/items/_searchForm', [
                'query' => '',
                'containerSearch' => false,
                'showExtraOptions' => false,
                'searchInside' => false,
                'containerId' => null,
            ]) ?>
        </div>

        <div id="idFormWrapper">
            <?= $this->render('/items/_idForm', [
                'id' => $itemId,
                'prevItem' => null,
                'nextItem' => null,
            ]) ?>
        </div>
    </div>


    <div class="alert alert-danger">
        <?= nl2br(Html::encode($message)) ?>
    </div>

    <p>The above error occurred while the Web server was processing your request.</p>

</div>
