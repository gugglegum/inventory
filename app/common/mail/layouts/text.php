<?php

/** @var \yii\web\View $this */
/** @var \yii\mail\MessageInterface $message */
/** @var string $content */
?>
<?php $this->beginPage() ?>
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
<?php $this->endPage() ?>
