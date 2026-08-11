<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use backend\assets\FancyboxAsset;
use tests\phpunit\TestCase;

/**
 * Проверяет локальную поставку и инициализацию современной версии Fancybox.
 */
final class FancyboxAssetTest extends TestCase
{
    public function testAssetUsesFancyboxSixWithoutJqueryPlugin(): void
    {
        $asset = new FancyboxAsset();

        self::assertSame(
            ['fancybox/6.1.14/fancybox.css'],
            $asset->css
        );
        self::assertSame(
            [
                'fancybox/6.1.14/fancybox.umd.js',
                'js/fancybox.init.js',
            ],
            $asset->js
        );

        $webRoot = dirname(__DIR__, 3) . '/backend/web';
        self::assertFileExists($webRoot . '/fancybox/6.1.14/fancybox.css');
        self::assertFileExists($webRoot . '/fancybox/6.1.14/fancybox.umd.js');
        self::assertFileDoesNotExist($webRoot . '/fancybox/jquery.fancybox.pack.js');

        $library = file_get_contents($webRoot . '/fancybox/6.1.14/fancybox.umd.js');
        $initializer = file_get_contents($webRoot . '/js/fancybox.init.js');
        self::assertIsString($library);
        self::assertIsString($initializer);
        self::assertStringContainsString('6.1.14', $library);
        self::assertStringContainsString("window.Fancybox.bind('[data-fancybox]'", $initializer);
        self::assertStringNotContainsString('jQuery', $initializer);
    }
}
