<?php

$params = yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/params.php',
    require dirname(__DIR__, 3) . '/console/config/params.php'
);

return yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/main.php',
    require __DIR__ . '/common.php',
    require dirname(__DIR__, 3) . '/console/config/main.php',
    [
        'id' => 'app-console-test',
        'params' => $params,
    ]
);
