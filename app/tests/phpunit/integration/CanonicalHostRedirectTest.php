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
        $canonicalHost = parse_url(
            (string) Yii::$app->params['auth']['canonicalOrigin'],
            PHP_URL_HOST
        );
        self::assertIsString($canonicalHost);

        Yii::$app->request->setHostInfo('http://' . $canonicalHost);
        Yii::$app->request->setUrl('/auth/sso/redirect');
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(200, Yii::$app->response->statusCode);
        self::assertNull(Yii::$app->response->headers->get('Location'));
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

    private function bootstrapCanonicalHost(): void
    {
        $component = new CanonicalHostRedirect([
            'canonicalOrigin' => Yii::$app->params['auth']['canonicalOrigin'],
            'oidcRedirectUri' => Yii::$app->params['oidc']['redirectUri'],
        ]);
        $component->bootstrap(Yii::$app);
    }
}
