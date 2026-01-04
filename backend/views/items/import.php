<?php

use common\models\Item;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

/** @var string $text */
/** @var Item $parent */
/** @var Repo $repo */
/** @var array[] $items */
/** @var int $errorLine */
/** @var string $errorStr */
/** @var string $errorMsg */

$this->title = 'Импорт предметов';
$this->render('/_breadcrumbs', ['item' => $parent, 'repo' => $repo]);
$this->params['breadcrumbs'][] = $this->title;

?>

<h1><?= Html::encode($this->title) ?></h1>

<p>В контейнер <?= Html::a($parent->name, Url::to(['items/view', 'repoId' => $repo->id, 'id' => $parent->itemId])) ?>&nbsp;<sup style="color: #999;">#<?= Html::encode($parent->id) ?></sup>
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
    'repo' => $repo,
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
