<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

/* @var $text string */
/* @var $parent \common\models\Item */
/* @var $items array[] */
/* @var $errorLine int */
/* @var $errorStr string */
/* @var $errorMsg string */

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
        <?php if (count($item) > 1) { ?>
        <ul>
            <?php foreach ($item as $key => $value) {
                if ($key === 'name') {
                    continue;
                }
            ?>
            <li><strong style="color: #999"><?= Html::encode($key) ?>:</strong> <?= nl2br(Html::encode($value)) ?></li>
            <?php } ?>
        </ul>
        <?php } ?>
    </li>
<?php } ?>
</ol>

<?php if (!empty($errorLine) && !empty($errorStr)) { ?>
<p>Ошибка в строке <?= $errorLine ?>: <?= Html::encode($errorMsg) ?></p>
<pre><?= Html::encode($errorStr) ?></pre>
<?php } ?>

<?= $this->render('_importForm', [
    'parent' => $parent,
    'text' => $text,
]) ?>

<p>Пример:</p>

<pre style="color: #666;">
Клавиатура Microsoft Wired 600
!Куплена в DNS за 1500 руб.
#периферия, компьютерное железо, клава, keyboard

Компьютерная мышь Logitech G MX518 Legendary
!Куплена в м.Видео за 3500 руб.
!Возрождённая легенда.
#периферия, компьютерное железо, мышка, мишустин, mx-518, mouse
</pre>
