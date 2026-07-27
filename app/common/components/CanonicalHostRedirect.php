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

    private string $canonicalHost;

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
        $this->canonicalHost = $canonical['host'];
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
        $requestHost = $request->getHostName();
        if (is_string($requestHost) && strcasecmp($requestHost, $this->canonicalHost) === 0) {
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

    /**
     * @return array{origin:string,host:string}
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
        $defaultPort = ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
        $portSuffix = is_int($port) && !$defaultPort ? ':' . $port : '';

        return [
            'origin' => $scheme . '://' . $host . $portSuffix,
            'host' => $host,
        ];
    }
}
