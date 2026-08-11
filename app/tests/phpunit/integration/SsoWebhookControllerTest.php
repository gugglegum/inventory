<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\SsoWebhookController;
use common\models\User;
use common\services\SsoAccessWebhookHandler;
use JsonException;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Проверяет совместимость Stockhub с profile/access webhook контрактом Pyrda SSO.
 */
final class SsoWebhookControllerTest extends DbTestCase
{
    private const string ISSUER = 'https://sso.example.test';

    private const string PROFILE_SECRET = 'profile-webhook-secret-at-least-32-bytes';

    private const string ACCESS_SECRET = 'access-webhook-secret-at-least-32-bytes';

    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->params['oidc']['issuer'] = self::ISSUER;
        Yii::$app->params['ssoWebhooks'] = [
            'profile' => [
                'secret' => self::PROFILE_SECRET,
                'timestampToleranceSeconds' => 300,
            ],
            'access' => [
                'secret' => self::ACCESS_SECRET,
                'timestampToleranceSeconds' => 300,
            ],
        ];
    }

    public function testProfileWebhookUpdatesLinkedUserAndClaimsCache(): void
    {
        $user = $this->linkedUser('profile-subject');
        $eventId = $this->eventId(1);

        $response = $this->sendProfile($eventId, [
            'sub' => 'profile-subject',
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'preferred_username' => 'updated-user',
            'profile_version' => 4,
        ]);

        self::assertSame(204, $response->statusCode);
        $user->refresh();
        self::assertSame('updated-user', $user->username);
        self::assertSame('updated@example.test', $user->email);
        self::assertSame(4, (int) $user->ssoProfileVersion);
        $claims = json_decode((string) $user->ssoClaims, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('Updated Name', $claims['name'] ?? null);
        self::assertSame(4, $claims['profile_version'] ?? null);
        self::assertSame(1, $this->deliveryCount('sso_profile_webhook_delivery', $eventId));
    }

    public function testProfileWebhookIsIdempotentAndIgnoresStaleVersion(): void
    {
        $user = $this->linkedUser('profile-version-subject');
        $user->updateAttributes(['ssoProfileVersion' => 5]);
        $eventId = $this->eventId(2);
        $payload = [
            'sub' => 'profile-version-subject',
            'name' => 'Stale Name',
            'email' => 'stale@example.test',
            'preferred_username' => 'stale-user',
            'profile_version' => 4,
        ];

        self::assertSame(204, $this->sendProfile($eventId, $payload)->statusCode);
        self::assertSame(204, $this->sendProfile($eventId, $payload)->statusCode);

        $user->refresh();
        self::assertNotSame('stale-user', $user->username);
        self::assertSame(5, (int) $user->ssoProfileVersion);
        self::assertSame(1, $this->deliveryCount('sso_profile_webhook_delivery', $eventId));
    }

    public function testAccessRevokedBlocksAllLoginPathsAndRotatesAuthKey(): void
    {
        $user = $this->linkedUser('revoked-subject');
        $oldAuthKey = $user->authKey;
        $eventId = $this->eventId(3);

        $response = $this->sendAccess(
            $eventId,
            SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
            ['sub' => 'revoked-subject', 'access_version' => 2],
            'disabled',
        );

        self::assertSame(204, $response->statusCode);
        $user->refresh();
        self::assertNotNull($user->ssoDisabledAt);
        self::assertSame(2, (int) $user->ssoAccessVersion);
        self::assertNotSame($oldAuthKey, $user->authKey);
        self::assertNull(User::findIdentity($user->id));
        self::assertNull(User::findByUsername($user->username));
        self::assertSame(1, $this->deliveryCount('sso_access_webhook_delivery', $eventId));
    }

    public function testAccessRestoredClearsBlockWithoutLoggingUserIn(): void
    {
        $user = $this->linkedUser('restored-subject');
        $user->updateAttributes([
            'ssoDisabledAt' => time() - 60,
            'ssoAccessVersion' => 2,
        ]);
        $authKey = $user->authKey;

        $response = $this->sendAccess(
            $this->eventId(4),
            SsoAccessWebhookHandler::EVENT_ACCESS_RESTORED,
            ['sub' => 'restored-subject', 'access_version' => 3],
            'enabled',
        );

        self::assertSame(204, $response->statusCode);
        $user->refresh();
        self::assertNull($user->ssoDisabledAt);
        self::assertSame(3, (int) $user->ssoAccessVersion);
        self::assertSame($authKey, $user->authKey);
        self::assertTrue(Yii::$app->user->isGuest);
        self::assertNotNull(User::findIdentity($user->id));
    }

    public function testSessionsRevokedRotatesAuthKeyWithoutChangingAccessState(): void
    {
        $user = $this->linkedUser('session-subject');
        $oldAuthKey = $user->authKey;

        $response = $this->sendAccess(
            $this->eventId(5),
            SsoAccessWebhookHandler::EVENT_SESSIONS_REVOKED,
            ['sub' => 'session-subject', 'session_version' => 7],
            'logout_everywhere',
        );

        self::assertSame(204, $response->statusCode);
        $user->refresh();
        self::assertSame(7, (int) $user->ssoSessionVersion);
        self::assertNull($user->ssoDisabledAt);
        self::assertNotSame($oldAuthKey, $user->authKey);
    }

    public function testAccessVersionsAreIndependentAndStaleEventsAreIgnored(): void
    {
        $user = $this->linkedUser('independent-versions-subject');
        $user->updateAttributes([
            'ssoAccessVersion' => 5,
            'ssoSessionVersion' => 8,
        ]);
        $authKey = $user->authKey;

        $this->sendAccess(
            $this->eventId(6),
            SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
            ['sub' => 'independent-versions-subject', 'access_version' => 4],
            'stale-disable',
        );
        $this->sendAccess(
            $this->eventId(7),
            SsoAccessWebhookHandler::EVENT_SESSIONS_REVOKED,
            ['sub' => 'independent-versions-subject', 'session_version' => 7],
            'stale-global-logout',
        );

        $user->refresh();
        self::assertNull($user->ssoDisabledAt);
        self::assertSame(5, (int) $user->ssoAccessVersion);
        self::assertSame(8, (int) $user->ssoSessionVersion);
        self::assertSame($authKey, $user->authKey);
    }

    public function testUnknownSubjectIsAcknowledgedWithoutCreatingUser(): void
    {
        $usersBefore = User::find()->count();
        $eventId = $this->eventId(8);

        $response = $this->sendAccess(
            $eventId,
            SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
            ['sub' => 'unknown-subject', 'access_version' => 2],
            'disabled',
        );

        self::assertSame(204, $response->statusCode);
        self::assertSame($usersBefore, User::find()->count());
        self::assertSame(1, $this->deliveryCount('sso_access_webhook_delivery', $eventId));
    }

    public function testInvalidSignatureIsRejectedBeforeDeliveryIsRecorded(): void
    {
        $eventId = $this->eventId(9);

        try {
            $this->sendAccess(
                $eventId,
                SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
                ['sub' => 'unknown-subject', 'access_version' => 2],
                'disabled',
                'sha256=' . str_repeat('0', 64),
            );
            self::fail('Webhook with invalid signature was accepted.');
        } catch (UnauthorizedHttpException $exception) {
            self::assertSame(401, $exception->statusCode);
        }

        self::assertSame(0, $this->deliveryCount('sso_access_webhook_delivery', $eventId));
    }

    public function testStaleTimestampIsRejectedBeforeDeliveryIsRecorded(): void
    {
        $eventId = $this->eventId(10);

        try {
            $this->sendAccess(
                $eventId,
                SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
                ['sub' => 'unknown-subject', 'access_version' => 2],
                'disabled',
                timestamp: time() - 301,
            );
            self::fail('Webhook with stale timestamp was accepted.');
        } catch (UnauthorizedHttpException $exception) {
            self::assertSame(401, $exception->statusCode);
        }

        self::assertSame(0, $this->deliveryCount('sso_access_webhook_delivery', $eventId));
    }

    public function testDeliveryHeaderMustMatchPayloadEventId(): void
    {
        $payloadEventId = $this->eventId(11);
        $headerEventId = $this->eventId(12);
        $payload = json_encode([
            'event_id' => $payloadEventId,
            'event_type' => SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
            'occurred_at' => '2026-08-12T12:00:00Z',
            'user' => ['sub' => 'unknown-subject', 'access_version' => 2],
            'reason' => 'disabled',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        try {
            $this->send(
                'access',
                $headerEventId,
                SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
                $payload,
                self::ACCESS_SECRET,
            );
            self::fail('Webhook with mismatched delivery id was accepted.');
        } catch (BadRequestHttpException $exception) {
            self::assertSame(400, $exception->statusCode);
        }

        self::assertSame(0, $this->deliveryCount('sso_access_webhook_delivery', $payloadEventId));
        self::assertSame(0, $this->deliveryCount('sso_access_webhook_delivery', $headerEventId));
    }

    public function testMissingReceiverSecretReturnsServiceUnavailable(): void
    {
        Yii::$app->params['ssoWebhooks']['access']['secret'] = '';
        $eventId = $this->eventId(13);

        try {
            $this->sendAccess(
                $eventId,
                SsoAccessWebhookHandler::EVENT_ACCESS_REVOKED,
                ['sub' => 'unknown-subject', 'access_version' => 2],
                'disabled',
            );
            self::fail('Webhook was accepted without configured receiver secret.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->statusCode);
        }

        self::assertSame(0, $this->deliveryCount('sso_access_webhook_delivery', $eventId));
    }

    public function testProfileConflictRollsBackDeliveryForRetry(): void
    {
        $linkedUser = $this->linkedUser('profile-conflict-subject');
        $otherUser = $this->createUser([
            'username' => 'occupied-profile-name',
            'email' => 'occupied-profile@example.test',
        ]);
        $eventId = $this->eventId(14);

        try {
            $this->sendProfile($eventId, [
                'sub' => 'profile-conflict-subject',
                'name' => 'Conflicting Profile',
                'email' => $otherUser->email,
                'preferred_username' => $otherUser->username,
                'profile_version' => 2,
            ]);
            self::fail('Conflicting SSO profile was applied.');
        } catch (ConflictHttpException $exception) {
            self::assertSame(409, $exception->statusCode);
        }

        $linkedUser->refresh();
        self::assertNotSame($otherUser->username, $linkedUser->username);
        self::assertSame(0, $this->deliveryCount('sso_profile_webhook_delivery', $eventId));
    }

    /**
     * @param array<string, mixed> $userPayload
     * @throws JsonException
     */
    private function sendProfile(string $eventId, array $userPayload): Response
    {
        $payload = json_encode([
            'event_id' => $eventId,
            'event_type' => 'user.profile.updated',
            'occurred_at' => '2026-08-12T12:00:00Z',
            'user' => $userPayload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $this->send('profile', $eventId, 'user.profile.updated', $payload, self::PROFILE_SECRET);
    }

    /**
     * @param array<string, mixed> $userPayload
     * @throws JsonException
     */
    private function sendAccess(
        string $eventId,
        string $eventType,
        array $userPayload,
        string $reason,
        ?string $signature = null,
        ?int $timestamp = null,
    ): Response {
        $payload = json_encode([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'occurred_at' => '2026-08-12T12:00:00Z',
            'user' => $userPayload,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $this->send(
            'access',
            $eventId,
            $eventType,
            $payload,
            self::ACCESS_SECRET,
            $signature,
            $timestamp,
        );
    }

    private function send(
        string $action,
        string $eventId,
        string $eventType,
        string $payload,
        string $secret,
        ?string $signature = null,
        ?int $timestamp = null,
    ): Response {
        $this->setPostRequest([], [], '/sso/' . $action . '-webhook');
        Yii::$app->request->setRawBody($payload);
        $timestampHeader = (string) ($timestamp ?? time());
        $headers = Yii::$app->request->headers;
        $headers->set('X-SSO-Event', $eventType);
        $headers->set('X-SSO-Delivery', $eventId);
        $headers->set('X-SSO-Timestamp', $timestampHeader);
        $headers->set(
            'X-SSO-Signature',
            $signature ?? 'sha256=' . hash_hmac('sha256', $timestampHeader . '.' . $payload, $secret)
        );

        $response = (new SsoWebhookController('sso-webhook', Yii::$app))->runAction($action);
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    private function linkedUser(string $subject): User
    {
        $user = $this->createUser();
        $user->updateAttributes([
            'ssoIssuer' => self::ISSUER,
            'ssoSubject' => $subject,
            'ssoClaims' => json_encode(['sub' => $subject], JSON_THROW_ON_ERROR),
        ]);

        return $user;
    }

    private function deliveryCount(string $table, string $eventId): int
    {
        self::assertContains($table, [
            'sso_profile_webhook_delivery',
            'sso_access_webhook_delivery',
        ]);
        $quotedTable = Yii::$app->db->quoteTableName($table);

        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM {$quotedTable} WHERE [[eventId]] = :eventId",
            [':eventId' => $eventId],
        )->queryScalar();
    }

    private function eventId(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
