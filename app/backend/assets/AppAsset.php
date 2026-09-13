<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace backend\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/bootstrap-compat.css',
        'css/site.css',
    ];
    public $js = [
        ['js/theme.js', 'position' => View::POS_HEAD, 'appendTimestamp' => true],
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
        'yii\bootstrap5\BootstrapIconAsset',
    ];
    public $cssOptions = [
        'appendTimestamp' => true,
    ];
}
