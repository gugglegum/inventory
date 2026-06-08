<?php

declare(strict_types=1);

namespace tests\phpunit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Application;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
    }

    protected function tearDown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->destroyApplication();
        parent::tearDown();
    }

    protected function appConfig(): array
    {
        return require __DIR__ . '/config/backend.php';
    }

    protected function mockApplication(array $config = []): Application
    {
        return new Application(ArrayHelper::merge($this->appConfig(), $config));
    }

    protected function destroyApplication(): void
    {
        if (Yii::$app !== null) {
            Yii::$app->errorHandler->unregister();
            Yii::$app->db->close();
        }
        Yii::$app = null;
    }

    protected function setGetRequest(array $queryParams = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Yii::$app->request->setQueryParams($queryParams);
        Yii::$app->request->setBodyParams([]);
    }

    protected function setPostRequest(array $bodyParams = [], array $queryParams = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->request->setQueryParams($queryParams);
        Yii::$app->request->setBodyParams($bodyParams);
    }
}
