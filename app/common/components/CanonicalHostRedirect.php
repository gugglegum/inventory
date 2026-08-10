<?php

declare(strict_types=1);

namespace common\components;

use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\web\Application as WebApplication;
use yii\web\Request;

/**
 * Перенаправляет все alias-hostnames на единственный origin до запуска сессии.
 *
 * OIDC state хранится в host-only session cookie, поэтому один deployment не
 * должен обслуживать несколько независимых hostname без раннего redirect.
 */
final class CanonicalHostRedirect extends Component implements BootstrapInterface
{
    public string $canonicalOrigin = '';

    public string $oidcRedirectUri = '';

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        $canonical = $this->parseUrl($this->canonicalOrigin, true, 'canonical origin');
        $redirect = $this->parseUrl($this->oidcRedirectUri, false, 'OIDC redirect URI');

        if ($canonical['origin'] !== $redirect['origin']) {
            throw new InvalidConfigException(
                'OIDC redirect URI must use the configured Stockhub canonical origin.'
            );
        }

        $this->canonicalOrigin = $canonical['origin'];
    }

    public function bootstrap($app): void
    {
        if (!$app instanceof WebApplication) {
            return;
        }

        $app->on(Application::EVENT_BEFORE_REQUEST, $this->redirectAlias(...));
    }

    /**
     * @throws ExitException
     */
    private function redirectAlias(Event $event): void
    {
        $app = $event->sender;
        if (!$app instanceof WebApplication) {
            return;
        }

        $request = $app->getRequest();
        $requestOrigin = $this->effectiveRequestOrigin($request);

        if ($requestOrigin === $this->canonicalOrigin) {
            return;
        }

        $requestUrl = $request->getUrl();
        if (!str_starts_with($requestUrl, '/')) {
            $requestUrl = '/' . $requestUrl;
        }

        $app->getResponse()->redirect($this->canonicalOrigin . $requestUrl, 308);

        // Application::run() поймает ExitException и отправит уже готовый redirect.
        throw new ExitException(0);
    }

    private function effectiveRequestOrigin(Request $request): ?string
    {
        $requestHostInfo = $request->getHostInfo();
        if (!is_string($requestHostInfo)) {
            return null;
        }

        try {
            $requestUrl = $this->parseUrl($requestHostInfo, true, 'request origin');
        } catch (InvalidConfigException) {
            // Некорректный Host также уводится на заранее настроенный origin.
            return null;
        }

        // Yii getHostInfo() учитывает forwarded scheme/host, но не добавляет
        // X-Forwarded-Port, когда Host уже присутствует.
        if (!$request->getHeaders()->has('X-Forwarded-Port')) {
            return $requestUrl['origin'];
        }

        $forwardedPortHeader = $request->getHeaders()->get('X-Forwarded-Port');
        if (
            !is_string($forwardedPortHeader)
            || preg_match('/^[0-9]{1,5}$/D', $forwardedPortHeader) !== 1
        ) {
            return null;
        }
        $forwardedPort = (int) $forwardedPortHeader;
        if ($forwardedPort < 1 || $forwardedPort > 65535) {
            return null;
        }

        return $this->formatOrigin(
            $requestUrl['scheme'],
            $requestUrl['host'],
            $forwardedPort,
        );
    }

    /**
     * @return array{origin:string,scheme:string,host:string,port:int|null}
     * @throws InvalidConfigException
     */
    private function parseUrl(string $url, bool $originOnly, string $field): array
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';

        if (
            !is_array($parts)
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
            || !is_string($host)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || ($originOnly && !in_array($path, ['', '/'], true))
            || ($originOnly && isset($parts['query']))
        ) {
            throw new InvalidConfigException("Stockhub {$field} must be a valid HTTP(S) URL.");
        }

        $scheme = strtolower($scheme);
        $host = strtolower($host);
        $port = $parts['port'] ?? null;

        return [
            'origin' => $this->formatOrigin($scheme, $host, $port),
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
        ];
    }

    private function formatOrigin(string $scheme, string $host, ?int $port): string
    {
        $defaultPort = ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
        $portSuffix = is_int($port) && !$defaultPort ? ':' . $port : '';

        return $scheme . '://' . $host . $portSuffix;
    }
}
