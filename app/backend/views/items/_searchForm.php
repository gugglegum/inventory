<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var ?string $query */
/** @var ?string $descriptionQuery */
/** @var ?string $notesQuery */
/** @var bool $containerSearch */
/** @var bool $showExtraOptions */
/** @var bool $searchInside */
/** @var int $containerId */
/** @var Repo $repo */

$this->registerCssFile('@web/css/search-form.css', ['appendTimestamp' => true], 'search-form');

$descriptionQuery ??= '';
$notesQuery ??= '';
$hasAdvancedQuery = trim($descriptionQuery) !== '' || trim($notesQuery) !== '';
if (!$containerSearch) {
    $this->registerJsFile('@web/js/search-form.js', ['appendTimestamp' => true], 'search-form');
}
$tabIndex = 1;
?>
<form action="<?= Html::encode(Url::to($containerSearch ? ['items/search-container', 'repoId' => $repo->id] : ['items/search', 'repoId' => $repo->id])) ?>" id="itemSearchForm">
    <div class="primary-search-fields">
        <label for="inputQuery">Я ищу:</label>
        <input id="inputQuery" type="text" name="q" value="<?= Html::encode($query) ?>" tabindex="<?= $tabIndex ?>" />
        <input type="submit" name="" value="Найти" tabindex="<?= $tabIndex + 2 ?>" />
    </div>
<?php if (!$containerSearch || $showExtraOptions) { ?>
    <div class="search-options-row">
    <?php if (!$containerSearch) { ?>
        <details id="advancedSearchOptions"<?= $hasAdvancedQuery ? ' open' : '' ?>>
            <summary>Расширенный поиск</summary>
            <div class="advanced-search-field">
                <label for="inputDescriptionQuery">В описании:</label>
                <input id="inputDescriptionQuery" type="text" name="description" value="<?= Html::encode($descriptionQuery) ?>"<?= $hasAdvancedQuery ? '' : ' disabled' ?> tabindex="<?= $tabIndex + 3 ?>" />
            </div>
            <div class="advanced-search-field">
                <label for="inputNotesQuery">В заметках:</label>
                <input id="inputNotesQuery" type="text" name="notes" value="<?= Html::encode($notesQuery) ?>"<?= $hasAdvancedQuery ? '' : ' disabled' ?> tabindex="<?= $tabIndex + 4 ?>" />
            </div>
        </details>
    <?php } ?>
    <?php if ($showExtraOptions) { ?>
        <div id="divExtraSearchOptions">
            <input type="checkbox" id="chkSearchInside" name="c" value="<?= $containerId ?>"<?= $searchInside ? ' checked' : ''?> tabindex="<?= $tabIndex + 1 ?>" /><label for="chkSearchInside">Искать внутри</label>
        </div>
    <?php } ?>
    </div>
<?php } ?>
</form>
