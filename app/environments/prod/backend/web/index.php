<?php

declare(strict_types=1);

if (!defined('YII_ENV')) {
    define('YII_ENV', getenv('YII_ENV') ?: 'prod');
}
if (!defined('YII_DEBUG')) {
    define('YII_DEBUG', (bool) getenv('YII_DEBUG'));
}

$activeEnvironment = constant('YII_ENV');
$debugEnabled = constant('YII_DEBUG');
if (!is_string($activeEnvironment) || !in_array($activeEnvironment, ['dev', 'prod'], true)) {
    throw new RuntimeException('YII_ENV must be either "dev" or "prod".');
}
if (!is_bool($debugEnabled)) {
    throw new RuntimeException('YII_DEBUG must be a boolean value.');
}
if ($activeEnvironment === 'prod' && $debugEnabled) {
    throw new RuntimeException('Yii debug mode cannot be enabled in production.');
}

require(__DIR__ . '/../../vendor/autoload.php');
require(__DIR__ . '/../../vendor/yiisoft/yii2/Yii.php');
require(__DIR__ . '/../../common/config/bootstrap.php');
require(__DIR__ . '/../config/bootstrap.php');

$config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/main.php'),
    require(__DIR__ . '/../../common/config/main-local.php'),
    require(__DIR__ . '/../config/main.php'),
    require(__DIR__ . '/../config/main-local.php')
);

$application = new yii\web\Application($config);
$application->run();
