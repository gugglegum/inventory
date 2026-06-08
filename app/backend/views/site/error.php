<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var \Throwable $exception */

use yii\helpers\Html;

$this->title = $name;

?>
<div class="site-error">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-danger">
        <?= nl2br(Html::encode($message)) ?>
    </div>

    <p>The above error occurred while the Web server was processing your request.</p>

</div>
