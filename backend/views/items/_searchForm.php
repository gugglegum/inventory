<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $query */
/** @var bool $containerSearch */
/** @var bool $showExtraOptions */
/** @var bool $searchInside */
/** @var int $containerId */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

?>
<form action="<?= Html::encode(Url::to($containerSearch ? ['items/search-container'] : ['items/search'])) ?>" id="itemSearchForm">
    <div>
        <label for="inputQuery">Я ищу:</label>
        <input id="inputQuery" type="text" name="q" value="<?= Html::encode($query) ?>" />
        <input type="submit" name="" value="Найти" />
    </div>
<?php if ($showExtraOptions) { ?>
    <div id="divExtraSearchOptions">
        <input type="checkbox" id="chkSearchInside" name="c" value="<?= $containerId ?>"<?= $searchInside ? ' checked' : ''?> /><label for="chkSearchInside">Искать внутри</label>
    </div>
<?php } ?>
</form>
