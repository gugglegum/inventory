<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $query string */

?>
<form action="<?= Html::encode(Url::to(['items/search-container'])) ?>" id="itemSearchForm">
    <div>
        <label for="query">Я ищу:</label>
        <input id="query" type="text" name="q" value="<?= Html::encode($query) ?>" />
        <input type="submit" name="" value="Найти" />
    </div>
</form>
