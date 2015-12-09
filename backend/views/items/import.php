<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

/* @var $text string */
/* @var $parent \common\models\Item */
/* @var $items \common\models\Item[] */
/* @var $errorLine int */
/* @var $errorStr string */

$this->title = 'Импорт предметов';
$this->render('_breadcrumbs', ['model' => $parent]);
$this->params['breadcrumbs'][] = $this->title;

?>

<h1><?= Html::encode($this->title) ?></h1>

<p>В контейнер <?= Html::a($parent->name, Url::to(['items/view', 'id' => $parent->id])) ?>&nbsp;<sup style="color: #999;">#<?= Html::encode($parent->id) ?></sup>
    планируется добавить следующие предметы. Проверьте всё ли правильно, исправьте если необходимо, отметьте галочку
    &laquo;подтвердить добавление&raquo; и нажмите &laquo;Импорт&raquo;.</p>

<ol>
<?php
foreach ($items as $item) {
?>
    <li>
        <div style="font-size: 120%; font-weight: bold;"><?= Html::encode($item['name']) ?></div>
        <?php if (count($items) > 1) { ?>
        <ul>
            <?php foreach ($item as $key => $value) {
                if ($key === 'name') {
                    continue;
                }
            ?>
            <li><strong style="color: #999"><?= Html::encode($key) ?>:</strong> <?= Html::encode($value) ?></li>
            <?php } ?>
        </ul>
        <?php } ?>
    </li>
<?php } ?>
</ol>

<?php if (!empty($errorLine) && !empty($errorStr)) { ?>
<p>Ошибка в строке <?= $errorLine ?>:</p>
<pre><?= Html::encode($errorStr) ?></pre>
<?php } ?>

<?= $this->render('_importForm', [
    'parent' => $parent,
    'text' => $text,
]) ?>
