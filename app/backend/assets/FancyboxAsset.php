<?php

declare(strict_types=1);

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Fancybox 6 для просмотра постоянных и временно загруженных фотографий.
 */
final class FancyboxAsset extends AssetBundle
{
    public $basePath = '@webroot';

    public $baseUrl = '@web';

    public $css = [
        'fancybox/6.1.14/fancybox.css',
    ];

    public $js = [
        'fancybox/6.1.14/fancybox.umd.js',
        'js/fancybox.init.js',
    ];

    public $cssOptions = [
        'appendTimestamp' => true,
        'media' => 'screen',
    ];

    public $jsOptions = [
        'appendTimestamp' => true,
    ];
}
