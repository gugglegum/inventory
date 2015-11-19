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

$this->registerCssFile('@web/fancybox/jquery.fancybox.css', ['media' => 'screen'], 'fancybox');
$this->registerJsFile('@web/fancybox/jquery.fancybox.pack.js', ['depends' => [\yii\web\JqueryAsset::className()]], 'fancybox');
$this->registerJsFile('@web/js/fancybox.init.js', ['depends' => [\yii\web\JqueryAsset::className()]], 'fancybox.init');

