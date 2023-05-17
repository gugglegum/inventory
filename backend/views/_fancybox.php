<?php
/*
 * Подключалка Fancybox
 *
 * Данный partial используется для простого подключения jQuery-плагина Fancybox. Чтобы вместо 5 одинаковых строчек
 * в нескольких файлах была только одна:
 *
 * $this->render('//_fancybox');
 *
 * Также если нужно будет что-то поменять или отключить, то достаточно будет поправить это в одном месте.
 */

$this->registerCssFile('@web/fancybox/jquery.fancybox.css', ['appendTimestamp' => true, 'media' => 'screen'], 'fancybox');
$this->registerJsFile('@web/fancybox/jquery.fancybox.pack.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'fancybox');
$this->registerJsFile('@web/js/fancybox.init.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'fancybox.init');
