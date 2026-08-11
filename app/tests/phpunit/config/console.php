<?php

declare(strict_types=1);

$testCommonConfig = require __DIR__ . '/common.php';

$testParams = yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/params.php',
    require dirname(__DIR__, 3) . '/console/config/params.php',
    $testCommonConfig['params']
);

return yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/main.php',
    $testCommonConfig,
    require dirname(__DIR__, 3) . '/console/config/main.php',
    [
        'id' => 'app-console-test',
        'runtimePath' => '@phpunitRuntime/console',
        // console/config/main.php assigns its own local $params while required
        // in this scope, so the test value must use a distinct variable name.
        'params' => $testParams,
    ]
);
