<?php

declare(strict_types=1);

namespace tests\phpunit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Application;

/**
 * Базовый PHPUnit-класс для тестов, которым нужен тестовый Yii backend application.
 *
 * Создает приложение перед каждым тестом и гарантированно уничтожает его после теста,
 * чтобы состояние Yii::$app и request-параметры не протекали между сценариями.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Создает тестовое Yii-приложение перед выполнением сценария.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
    }

    /**
     * Возвращает request в безопасное состояние и уничтожает Yii-приложение.
     */
    protected function tearDown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $this->destroyApplication();
        parent::tearDown();
    }

    /**
     * Возвращает базовую конфигурацию приложения для тестов.
     */
    protected function appConfig(): array
    {
        return require __DIR__ . '/config/backend.php';
    }

    /**
     * Создает Yii backend application с возможностью точечно переопределить конфигурацию.
     *
     * @param array $config Дополнительная конфигурация, объединяемая с базовой тестовой.
     */
    protected function mockApplication(array $config = []): Application
    {
        return new Application(ArrayHelper::merge($this->appConfig(), $config));
    }

    /**
     * Закрывает соединение с БД и сбрасывает глобальный Yii::$app.
     */
    protected function destroyApplication(): void
    {
        /** @psalm-suppress RedundantConditionGivenDocblockType Yii::$app is intentionally reset between tests. */
        if (Yii::$app !== null) {
            Yii::$app->errorHandler->unregister();
            Yii::$app->db->close();
        }
        Yii::$app = null;
    }

    /**
     * Переключает тестовый request в GET-режим с заданными query-параметрами.
     *
     * @param array $queryParams GET-параметры для текущего тестового запроса.
     * @param string $requestUri URI текущего тестового запроса.
     */
    protected function setGetRequest(array $queryParams = [], string $requestUri = '/'): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $requestUri;
        Yii::$app->request->setQueryParams($queryParams);
        Yii::$app->request->setBodyParams([]);
    }

    /**
     * Переключает тестовый request в POST-режим с заданными body/query-параметрами.
     *
     * @param array $bodyParams POST-параметры тела запроса.
     * @param array $queryParams GET-параметры текущего запроса.
     * @param string $requestUri URI текущего тестового запроса.
     */
    protected function setPostRequest(array $bodyParams = [], array $queryParams = [], string $requestUri = '/'): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $requestUri;
        Yii::$app->request->setQueryParams($queryParams);
        Yii::$app->request->setBodyParams($bodyParams);
    }
}
