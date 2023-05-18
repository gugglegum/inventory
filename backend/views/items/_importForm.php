<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $parent common\models\Item */
/* @var $text string */

$this->registerCssFile('@web/css/import-form.css', ['appendTimestamp' => true], 'import-form');

?>
<?= Html::beginForm(Url::to(['items/import', 'parentId' => $parent->id]), 'post', ['id' => 'formImportItems', 'style' => "margin: 1.5em 0;"]) ?>
    <div>
        <label for="text">Импорт из текста:</label><br />
        <?= Html::textarea('text', $text, ['rows' => 12, 'style' => 'font-size: 90%']) ?><br />
        <?= Html::checkbox('confirm', false, ['label' => 'Подтвердить добавление']) ?><br />
        <?= Html::submitInput('Импорт') ?>
    </div>
<?= Html::endForm() ?>
<p style="color: #999;">Каждая строка &mdash; наименование нового предмета. Если строка начинается с "!", то это описание
    к предмету выше. Если с "#", то это теги через запятую. Строк описания и тегов может быть несколько. Пустые строки
    игнорируются. Если не поставить галочку, то будет дополнительный шаг с подтверждением импорта.</p>
