<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\components\CanonicalHostRedirect;
use tests\phpunit\TestCase;
use Yii;
use yii\base\Application;
use yii\base\ExitException;
use yii\base\InvalidConfigException;

/**
 * Проверяет раннюю canonical-host защиту host-only OIDC session.
 */
final class CanonicalHostRedirectTest extends TestCase
{
    /** @var array<non-empty-string, string|null> */
    private array $proxyServerBackup = [];

    /**
     * Alias перенаправляется до инициализации user/session компонентов.
     */
    public function testAliasRedirectsBeforeSessionIsCreatedAndPreservesRequestUrl(): void
    {
        $this->bootstrapCanonicalHost();
        Yii::$app->request->setHostInfo('https://p.stockhub.ru');
        Yii::$app->request->setUrl('/repo/42/items?view=compact');

        self::assertFalse(Yii::$app->has('session', true));
        self::assertFalse(Yii::$app->has('user', true));

        try {
            Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);
            self::fail('Alias host was not redirected.');
        } catch (ExitException $exception) {
            self::assertSame(0, $exception->statusCode);
        }

        $canonicalOrigin = Yii::$app->params['auth']['canonicalOrigin'];
        self::assertSame(308, Yii::$app->response->statusCode);
        self::assertSame(
            $canonicalOrigin . '/repo/42/items?view=compact',
            Yii::$app->response->headers->get('Location')
        );
        self::assertNull(Yii::$app->response->headers->get('Set-Cookie'));
        self::assertFalse(Yii::$app->has('session', true));
        self::assertFalse(Yii::$app->has('user', true));
    }

    /**
     * Canonical host продолжает обычную обработку запроса.
     */
    public function testCanonicalHostDoesNotRedirect(): void
    {
        $this->bootstrapCanonicalHost();
        $canonicalOrigin = (string) Yii::$app->params['auth']['canonicalOrigin'];

        Yii::$app->request->setHostInfo($canonicalOrigin);
        Yii::$app->request->setUrl('/auth/sso/redirect');
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(200, Yii::$app->response->statusCode);
        self::assertNull(Yii::$app->response->headers->get('Location'));
    }

    /**
     * Совпадение hostname недостаточно: HTTP переводится на canonical HTTPS origin.
     */
    public function testHttpCanonicalHostRedirectsToHttps(): void
    {
        $this->bootstrapCanonicalHost();
        Yii::$app->request->setHostInfo('http://stockhub.example.test');
        Yii::$app->request->setUrl('/auth/sso/redirect');

        $this->triggerRedirect();

        self::assertSame(308, Yii::$app->response->statusCode);
        self::assertSame(
            'https://stockhub.example.test/auth/sso/redirect',
            Yii::$app->response->headers->get('Location')
        );
    }

    /**
     * Port является частью origin и также должен совпадать.
     */
    public function testCanonicalPortMismatchRedirects(): void
    {
        $this->recreateApplicationBehindProxy(
            '10.20.30.40',
            ['10.0.0.0/8'],
            forwardedPort: '9443',
        );
        $this->bootstrapCanonicalHost(
            'https://stockhub.example.test:8443',
            'https://stockhub.example.test:8443/auth/sso/callback'
        );
        Yii::$app->request->setUrl('/repo/42');

        // Yii hostInfo intentionally demonstrates the framework behavior which
        // omits X-Forwarded-Port when Host is present.
        self::assertSame('https://stockhub.example.test', Yii::$app->request->getHostInfo());
        $this->triggerRedirect();

        self::assertSame(308, Yii::$app->response->statusCode);
        self::assertSame(
            'https://stockhub.example.test:8443/repo/42',
            Yii::$app->response->headers->get('Location')
        );
    }

    /**
     * Trusted X-Forwarded-Port дополняет effective origin за TLS proxy.
     */
    public function testTrustedProxyForwardedPortProvidesEffectiveCanonicalOrigin(): void
    {
        $this->recreateApplicationBehindProxy(
            '10.20.30.40',
            ['10.0.0.0/8'],
            forwardedPort: '8443',
        );
        $this->bootstrapCanonicalHost(
            'https://stockhub.example.test:8443',
            'https://stockhub.example.test:8443/auth/sso/callback'
        );
        Yii::$app->request->setUrl('/auth/sso/redirect');

        self::assertSame('https://stockhub.example.test', Yii::$app->request->getHostInfo());
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(200, Yii::$app->response->statusCode);
        self::assertNull(Yii::$app->response->headers->get('Location'));
    }

    /**
     * Некорректный trusted port не приводится к числу нестрогим PHP cast.
     */
    public function testInvalidTrustedForwardedPortCannotMatchCanonicalOrigin(): void
    {
        $this->recreateApplicationBehindProxy(
            '10.20.30.40',
            ['10.0.0.0/8'],
            forwardedPort: '8443evil',
        );
        $this->bootstrapCanonicalHost(
            'https://stockhub.example.test:8443',
            'https://stockhub.example.test:8443/auth/sso/callback'
        );
        Yii::$app->request->setUrl('/auth/sso/redirect');

        $this->triggerRedirect();

        self::assertSame(308, Yii::$app->response->statusCode);
        self::assertSame(
            'https://stockhub.example.test:8443/auth/sso/redirect',
            Yii::$app->response->headers->get('Location')
        );
    }

    /**
     * HTTPS от TLS-terminating proxy учитывается только для явно доверенного CIDR.
     */
    public function testTrustedProxyForwardedProtoProvidesEffectiveHttpsOrigin(): void
    {
        $this->recreateApplicationBehindProxy('10.20.30.40', ['10.0.0.0/8']);
        $this->bootstrapCanonicalHost();
        Yii::$app->request->setUrl('/auth/sso/redirect');

        self::assertSame('https://stockhub.example.test', Yii::$app->request->getHostInfo());
        self::assertSame('198.51.100.25', Yii::$app->request->getUserIP());
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(200, Yii::$app->response->statusCode);
        self::assertNull(Yii::$app->response->headers->get('Location'));
    }

    /**
     * Клиент не может подделать X-Forwarded-Proto за пределами trusted proxy CIDR.
     */
    public function testUntrustedForwardedProtoCannotSuppressHttpsRedirect(): void
    {
        $this->recreateApplicationBehindProxy('192.0.2.10', ['10.0.0.0/8']);
        $this->bootstrapCanonicalHost();
        Yii::$app->request->setUrl('/auth/sso/redirect');

        self::assertSame('http://stockhub.example.test', Yii::$app->request->getHostInfo());
        self::assertSame('192.0.2.10', Yii::$app->request->getUserIP());
        $this->triggerRedirect();

        self::assertSame(308, Yii::$app->response->statusCode);
        self::assertSame(
            'https://stockhub.example.test/auth/sso/redirect',
            Yii::$app->response->headers->get('Location')
        );
    }

    /**
     * Список доверенных proxy читается как явный comma-separated набор CIDR.
     */
    public function testTrustedProxyCidrsAreReadFromEnvironment(): void
    {
        $previous = getenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES=10.0.0.0/8, 2001:db8::/32');

        try {
            $params = require dirname(__DIR__, 3) . '/common/config/params.php';
        } finally {
            putenv(
                $previous === false
                    ? 'TRUSTED_PROXIES'
                    : 'TRUSTED_PROXIES=' . $previous
            );
        }

        self::assertSame(
            ['10.0.0.0/8', '2001:db8::/32'],
            $params['trustedProxies']
        );
    }

    /**
     * Callback другого origin отвергается при bootstrap, а не во время callback.
     */
    public function testOidcRedirectUriMustUseCanonicalOrigin(): void
    {
        try {
            new CanonicalHostRedirect([
                'canonicalOrigin' => 'https://stockhub.ru',
                'oidcRedirectUri' => 'https://p.stockhub.ru/auth/sso/callback',
            ]);
            self::fail('Mismatched OIDC redirect URI origin was accepted.');
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                'OIDC redirect URI must use the configured Stockhub canonical origin.',
                $exception->getMessage()
            );
        }
    }

    /**
     * Session cookie намеренно остается host-only.
     */
    public function testSessionCookieIsHostOnly(): void
    {
        $cookieParams = Yii::$app->session->getCookieParams();

        self::assertSame('', $cookieParams['domain'] ?? '');
    }

    private function bootstrapCanonicalHost(
        ?string $canonicalOrigin = null,
        ?string $oidcRedirectUri = null
    ): void {
        $component = new CanonicalHostRedirect([
            'canonicalOrigin' => $canonicalOrigin
                ?? Yii::$app->params['auth']['canonicalOrigin'],
            'oidcRedirectUri' => $oidcRedirectUri
                ?? Yii::$app->params['oidc']['redirectUri'],
        ]);
        $component->bootstrap(Yii::$app);
    }

    private function triggerRedirect(): void
    {
        try {
            Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);
            self::fail('Request with a non-canonical origin was not redirected.');
        } catch (ExitException $exception) {
            self::assertSame(0, $exception->statusCode);
        }
    }

    /**
     * @param list<string> $trustedProxyCidrs
     */
    private function recreateApplicationBehindProxy(
        string $remoteAddress,
        array $trustedProxyCidrs,
        ?string $forwardedPort = null,
    ): void {
        $this->destroyApplication();

        foreach ([
            'REMOTE_ADDR',
            'HTTP_HOST',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED_PROTO',
            'HTTP_X_FORWARDED_PORT',
            'SERVER_PORT',
            'HTTPS',
        ] as $key) {
            $value = $_SERVER[$key] ?? null;
            $this->proxyServerBackup[$key] = is_string($value) ? $value : null;
        }

        $_SERVER['REMOTE_ADDR'] = $remoteAddress;
        $_SERVER['HTTP_HOST'] = 'stockhub.example.test';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.25';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['SERVER_PORT'] = '80';
        if ($forwardedPort === null) {
            unset($_SERVER['HTTP_X_FORWARDED_PORT']);
        } else {
            $_SERVER['HTTP_X_FORWARDED_PORT'] = $forwardedPort;
        }
        unset($_SERVER['HTTPS']);

        $this->mockApplication([
            'components' => [
                'request' => [
                    'trustedHosts' => array_fill_keys(
                        $trustedProxyCidrs,
                        [
                            'X-Forwarded-For',
                            'X-Forwarded-Host',
                            'X-Forwarded-Proto',
                            'X-Forwarded-Port',
                        ]
                    ),
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->proxyServerBackup as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        $this->proxyServerBackup = [];

        parent::tearDown();
    }
}
