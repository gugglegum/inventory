<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var string $query */
/** @var bool $containerSearch */
/** @var bool $showExtraOptions */
/** @var bool $searchInside */
/** @var int $containerId */
/** @var Repo $repo */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

$tabIndex = 1;
?>
<form action="<?= Html::encode(Url::to($containerSearch ? ['items/search-container', 'repoId' => $repo->id] : ['items/search', 'repoId' => $repo->id])) ?>" id="itemSearchForm">
    <div>
        <label for="inputQuery">Я ищу:</label>
        <input id="inputQuery" type="text" name="q" value="<?= Html::encode($query) ?>" tabindex="<?= $tabIndex ?>" />
        <input type="submit" name="" value="Найти" tabindex="<?= $tabIndex + 2 ?>" />
    </div>
<?php if ($showExtraOptions) { ?>
    <div id="divExtraSearchOptions">
        <input type="checkbox" id="chkSearchInside" name="c" value="<?= $containerId ?>"<?= $searchInside ? ' checked' : ''?> tabindex="<?= $tabIndex + 1 ?>" /><label for="chkSearchInside">Искать внутри</label>
    </div>
<?php } ?>
</form>
