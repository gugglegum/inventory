<?php

declare(strict_types=1);

Yii::setAlias('phpunitRuntime', sys_get_temp_dir() . '/stockhub-phpunit-' . md5(__DIR__));

foreach ([
    '@phpunitRuntime/backend',
    '@phpunitRuntime/console',
    '@phpunitRuntime/assets',
    '@phpunitRuntime/photos/temp',
    '@phpunitRuntime/thumbnails/temp',
] as $runtimeAlias) {
    $runtimeDir = Yii::getAlias($runtimeAlias);
    if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0777, true) && !is_dir($runtimeDir)) {
        throw new RuntimeException("Cannot create PHPUnit runtime directory: {$runtimeDir}");
    }
}
