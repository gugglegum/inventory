<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use common\services\OidcException;
use common\services\OidcHttpTransportInterface;

/**
 * Детерминированный transport OIDC unit-тестов без сетевых запросов.
 */
final class FakeOidcHttpTransport implements OidcHttpTransportInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $getResponses = [];

    /** @var array<string, array<string, mixed>> */
    private array $postResponses = [];

    /** @var list<array{url: string, timeout: int}> */
    public array $getRequests = [];

    /** @var list<array{url: string, formData: array<string, string>, timeout: int}> */
    public array $postRequests = [];

    /**
     * @param array<string, mixed> $response
     */
    public function respondToGet(string $url, array $response): void
    {
        $this->getResponses[$url] = $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function respondToPost(string $url, array $response): void
    {
        $this->postResponses[$url] = $response;
    }

    /**
     * @inheritDoc
     */
    public function getJson(string $url, int $timeoutSeconds): array
    {
        $this->getRequests[] = [
            'url' => $url,
            'timeout' => $timeoutSeconds,
        ];

        if (!array_key_exists($url, $this->getResponses)) {
            throw new OidcException('Unexpected fake OIDC GET request.');
        }

        return $this->getResponses[$url];
    }

    /**
     * @inheritDoc
     */
    public function postForm(string $url, array $formData, int $timeoutSeconds): array
    {
        $this->postRequests[] = [
            'url' => $url,
            'formData' => $formData,
            'timeout' => $timeoutSeconds,
        ];

        if (!array_key_exists($url, $this->postResponses)) {
            throw new OidcException('Unexpected fake OIDC POST request.');
        }

        return $this->postResponses[$url];
    }
}
