<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $query string */

?>
<form action="<?= Html::encode(Url::to(['items/search'])) ?>" style="margin: 1.5em 0;">
    <div>
        <label for="query">Я ищу:</label>
        <input id="query" type="text" name="q" value="<?= Html::encode($query) ?>" style="width: 30em" />
        <input type="submit" name="" value="Найти" />
    </div>
</form>
