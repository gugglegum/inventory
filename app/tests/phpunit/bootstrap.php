<?php

declare(strict_types=1);

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';
require dirname(__DIR__, 2) . '/common/config/bootstrap.php';

Yii::setAlias('tests', dirname(__DIR__));
Yii::setAlias('phpunitTests', __DIR__);

require __DIR__ . '/TestCase.php';
require __DIR__ . '/DbTestCase.php';
