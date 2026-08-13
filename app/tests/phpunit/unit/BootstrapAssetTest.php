<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use backend\assets\AppAsset as BackendAppAsset;
use common\widgets\Alert as FlashAlert;
use frontend\assets\AppAsset as FrontendAppAsset;
use tests\phpunit\TestCase;
use Yii;
use yii\bootstrap5\BootstrapAsset;
use yii\bootstrap5\BootstrapIconAsset;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\web\YiiAsset;

/**
 * Проверяет локальное подключение Bootstrap 5 и Bootstrap Icons.
 */
final class BootstrapAssetTest extends TestCase
{
    public function testApplicationAssetsUseBootstrapFiveWithLocalIcons(): void
    {
        $expectedDependencies = [
            YiiAsset::class,
            BootstrapPluginAsset::class,
            BootstrapIconAsset::class,
        ];

        self::assertSame($expectedDependencies, (new BackendAppAsset())->depends);
        self::assertSame($expectedDependencies, (new FrontendAppAsset())->depends);
        self::assertSame(
            ['css/bootstrap-compat.css', 'css/site.css'],
            (new BackendAppAsset())->css
        );
        self::assertSame('5.x', Yii::$app->params['bsVersion']);

        $bootstrapAsset = new BootstrapAsset();
        $bootstrapPluginAsset = new BootstrapPluginAsset();
        $bootstrapIconAsset = new BootstrapIconAsset();
        $vendorPath = dirname(__DIR__, 3) . '/vendor';

        self::assertSame($vendorPath . '/twbs/bootstrap/dist/css', $bootstrapAsset->sourcePath);
        self::assertSame(['bootstrap.css'], $bootstrapAsset->css);
        self::assertSame($vendorPath . '/twbs/bootstrap/dist/js', $bootstrapPluginAsset->sourcePath);
        self::assertSame(['bootstrap.bundle.js'], $bootstrapPluginAsset->js);
        self::assertSame([BootstrapAsset::class], $bootstrapPluginAsset->depends);
        self::assertSame($vendorPath . '/twbs/bootstrap-icons/font', $bootstrapIconAsset->sourcePath);
        self::assertSame(['bootstrap-icons.css'], $bootstrapIconAsset->css);

        self::assertFileExists($vendorPath . '/twbs/bootstrap/dist/css/bootstrap.css');
        self::assertFileExists($vendorPath . '/twbs/bootstrap/dist/js/bootstrap.bundle.js');
        self::assertFileExists($vendorPath . '/twbs/bootstrap-icons/font/bootstrap-icons.css');
        self::assertFileExists($vendorPath . '/twbs/bootstrap-icons/font/fonts/bootstrap-icons.woff2');

        $backendWebPath = dirname(__DIR__, 3) . '/backend/web';
        self::assertFileExists($backendWebPath . '/css/bootstrap-compat.css');
    }

    public function testFlashAlertUsesBootstrapFiveDismissButton(): void
    {
        Yii::$app->session->setFlash('success', 'Изменения сохранены');

        $html = FlashAlert::widget();

        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('alert-dismissible', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('class="btn-close"', $html);
        self::assertStringContainsString('data-bs-dismiss="alert"', $html);
        self::assertStringNotContainsString('class="close"', $html);
        self::assertStringContainsString('Изменения сохранены', $html);
    }
}
