<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $query */
/** @var bool $containerSearch */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

?>
<form action="<?= Html::encode(Url::to($containerSearch ? ['items/search-container'] : ['items/search'])) ?>" id="itemSearchForm">
    <div>
        <label for="query">Я ищу:</label>
        <input id="query" type="text" name="q" value="<?= Html::encode($query) ?>" />
        <input type="submit" name="" value="Найти" />
    </div>
</form>
