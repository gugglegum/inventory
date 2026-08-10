<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\models\User;
use common\services\SsoUserLinkException;
use common\services\SsoUserLinker;
use console\controllers\UserController;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\phpunit\DbTestCase;
use Yii;
use yii\console\ExitCode;

/**
 * Integration-тесты безопасной привязки существующих пользователей к Pyrda SSO.
 */
final class SsoUserLinkerTest extends DbTestCase
{
    private const string ISSUER = 'https://sso.example.test';

    /**
     * Вход заранее привязанного пользователя обновляет только SSO-метаданные.
     */
    public function testAuthenticatesPrelinkedUserAndPreservesLocalCredentialsAndAccess(): void
    {
        $user = $this->createUser([
            'username' => 'local-user',
            'email' => 'linked@example.test',
            'password' => 'local-password',
            'access' => User::ACCESS_MANAGE_USERS | User::ACCESS_CREATE_REPO,
        ]);
        $originalId = $user->id;
        $originalUsername = $user->username;
        $originalEmail = $user->email;
        $originalPasswordHash = $user->passwordHash;
        $originalAuthKey = $user->authKey;
        $originalAccess = $user->access;
        $claims = [
            'iss' => self::ISSUER,
            'sub' => 'sso-subject-1',
            'email' => 'linked@example.test',
            'email_verified' => true,
            'name' => 'SSO User',
            'groups' => ['stockhub'],
        ];
        $beforeLink = time();
        $service = new SsoUserLinker();

        $prelinkedUser = $service->prelink('local-user', self::ISSUER, 'sso-subject-1');
        $linkedUser = $service->link($claims);

        self::assertSame($originalId, $prelinkedUser->id);
        self::assertSame($originalId, $linkedUser->id);
        self::assertSame($originalUsername, $linkedUser->username);
        self::assertSame($originalEmail, $linkedUser->email);
        self::assertSame($originalPasswordHash, $linkedUser->passwordHash);
        self::assertSame($originalAuthKey, $linkedUser->authKey);
        self::assertSame($originalAccess, $linkedUser->access);
        self::assertSame(self::ISSUER, $linkedUser->ssoIssuer);
        self::assertSame('sso-subject-1', $linkedUser->ssoSubject);
        self::assertSame(
            $claims,
            json_decode((string) $linkedUser->ssoClaims, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertGreaterThanOrEqual($beforeLink, (int) $linkedUser->lastSsoLoginAt);
        self::assertLessThanOrEqual(time(), (int) $linkedUser->lastSsoLoginAt);
        self::assertTrue($linkedUser->validatePassword('local-password'));
    }

    /**
     * Повторный вход находит пользователя по стабильному sub и обновляет снимок claims.
     */
    public function testReusesExistingBindingBySubjectAndDoesNotReplaceLocalEmail(): void
    {
        $user = $this->createUser([
            'username' => 'existing-binding',
            'email' => 'original@example.test',
        ]);
        $service = new SsoUserLinker();
        $service->prelink('existing-binding', self::ISSUER, 'stable-subject');
        $service->link([
            'iss' => self::ISSUER,
            'sub' => 'stable-subject',
            'email' => 'original@example.test',
            'email_verified' => true,
            'name' => 'Old Name',
        ]);
        $updatedClaims = [
            'iss' => self::ISSUER,
            'sub' => 'stable-subject',
            'email' => 'changed-in-sso@example.test',
            'email_verified' => true,
            'name' => 'New Name',
        ];

        $linkedUser = $service->link($updatedClaims);

        self::assertSame($user->id, $linkedUser->id);
        self::assertSame('original@example.test', $linkedUser->email);
        self::assertSame(
            $updatedClaims,
            json_decode((string) $linkedUser->ssoClaims, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Email claims не являются основанием административной привязки и не обязательны для входа.
     */
    public function testAuthenticatesWithoutEmailClaims(): void
    {
        $user = $this->createUser(['email' => 'local@example.test']);
        $service = new SsoUserLinker();
        $service->prelink('local@example.test', self::ISSUER, 'openid-only-subject');

        $linkedUser = $service->link([
            'iss' => self::ISSUER,
            'sub' => 'openid-only-subject',
        ]);

        self::assertSame($user->id, $linkedUser->id);
        self::assertSame(
            ['iss' => self::ISSUER, 'sub' => 'openid-only-subject'],
            json_decode((string) $linkedUser->ssoClaims, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Неизвестный subject не создает нового локального пользователя.
     */
    public function testRejectsUnknownUserWithoutCreatingIt(): void
    {
        $usersBefore = User::find()->count();

        try {
            (new SsoUserLinker())->link([
                'iss' => self::ISSUER,
                'sub' => 'unknown-subject',
                'email' => 'unknown@example.test',
                'email_verified' => true,
            ]);
            self::fail('Ожидалось исключение для неизвестного пользователя.');
        } catch (SsoUserLinkException $exception) {
            self::assertSame(
                'Активная учетная запись Stockhub не привязана к этому пользователю SSO.',
                $exception->getMessage()
            );
        } finally {
            self::assertSame($usersBefore, User::find()->count());
        }
    }

    /**
     * Совпадающий email не выполняет автоматическую привязку.
     */
    public function testMatchingEmailDoesNotAutoLinkLocalUser(): void
    {
        $user = $this->createUser(['email' => 'conflict@example.test']);

        try {
            (new SsoUserLinker())->link([
                'iss' => self::ISSUER,
                'sub' => 'different-subject',
                'email' => 'conflict@example.test',
                'email_verified' => true,
            ]);
            self::fail('Ожидался отказ для административно не привязанного subject.');
        } catch (SsoUserLinkException $exception) {
            self::assertSame(
                'Активная учетная запись Stockhub не привязана к этому пользователю SSO.',
                $exception->getMessage()
            );
        } finally {
            $user->refresh();
            self::assertNull($user->ssoSubject);
        }
    }

    /**
     * Удаленная локальная учетная запись не может быть восстановлена через SSO-вход.
     */
    public function testRejectsDeletedUser(): void
    {
        $user = $this->createUser(['email' => 'deleted@example.test']);
        $user->updateAttributes([
            'status' => User::STATUS_DELETED,
            'ssoIssuer' => self::ISSUER,
            'ssoSubject' => 'deleted-subject',
        ]);

        try {
            (new SsoUserLinker())->link([
                'iss' => self::ISSUER,
                'sub' => 'deleted-subject',
                'email' => 'deleted@example.test',
                'email_verified' => true,
            ]);
            self::fail('Ожидалось исключение для удаленного пользователя.');
        } catch (SsoUserLinkException $exception) {
            self::assertSame(
                'Активная учетная запись Stockhub не привязана к этому пользователю SSO.',
                $exception->getMessage()
            );
        }
    }

    /**
     * Административная привязка не может забрать subject у другой, даже удаленной, записи.
     */
    public function testPrelinkRejectsSubjectOwnedByAnotherUser(): void
    {
        $deletedUser = $this->createUser(['email' => 'old-binding@example.test']);
        $deletedUser->updateAttributes([
            'status' => User::STATUS_DELETED,
            'ssoIssuer' => self::ISSUER,
            'ssoSubject' => 'reserved-subject',
        ]);
        $activeUser = $this->createUser(['email' => 'new-binding@example.test']);

        try {
            (new SsoUserLinker())->prelink(
                'new-binding@example.test',
                self::ISSUER,
                'reserved-subject'
            );
            self::fail('Ожидалось доменное исключение для занятого subject.');
        } catch (SsoUserLinkException $exception) {
            self::assertSame(
                'Эта учетная запись OIDC уже привязана к другому пользователю Stockhub.',
                $exception->getMessage()
            );
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        $activeUser->refresh();
        self::assertNull($activeUser->ssoSubject);
    }

    /**
     * Повтор той же административной привязки идемпотентен, смена subject запрещена.
     */
    public function testPrelinkIsIdempotentAndRejectsRebinding(): void
    {
        $user = $this->createUser([
            'username' => 'prelinked-user',
            'email' => 'prelinked@example.test',
        ]);
        $service = new SsoUserLinker();

        self::assertSame(
            $user->id,
            $service->prelink('prelinked-user', self::ISSUER, 'stable-subject')->id
        );
        self::assertSame(
            $user->id,
            $service->prelink('prelinked@example.test', self::ISSUER, 'stable-subject')->id
        );

        try {
            $service->prelink('prelinked-user', self::ISSUER, 'different-subject');
            self::fail('Ожидался отказ при попытке сменить subject.');
        } catch (SsoUserLinkException $exception) {
            self::assertSame(
                'Учетная запись Stockhub уже привязана к другой учетной записи OIDC.',
                $exception->getMessage()
            );
        }

        $user->refresh();
        self::assertSame('stable-subject', $user->ssoSubject);
    }

    /**
     * Административная привязка хранит тот же canonical issuer, который проверяет web-runtime.
     */
    public function testPrelinkCanonicalizesIssuerTrailingSlash(): void
    {
        $user = $this->createUser(['username' => 'canonical-issuer']);
        $service = new SsoUserLinker();

        $linkedUser = $service->prelink(
            'canonical-issuer',
            self::ISSUER . '/',
            'canonical-subject'
        );

        self::assertSame(self::ISSUER, $linkedUser->ssoIssuer);
        self::assertSame(
            $user->id,
            $service->link([
                'iss' => self::ISSUER,
                'sub' => 'canonical-subject',
            ])->id
        );
    }

    /**
     * CLI-команда сообщает и сохраняет canonical issuer, а не исходное значение из params.
     */
    public function testLinkSsoCommandCanonicalizesConfiguredIssuer(): void
    {
        $user = $this->createUser(['username' => 'cli-canonical-issuer']);
        Yii::$app->params['oidc']['issuer'] = self::ISSUER . '/';
        $controller = new UserController('user', Yii::$app);

        ob_start();
        try {
            $exitCode = $controller->actionLinkSso('cli-canonical-issuer', 'cli-subject');
        } finally {
            $output = (string) ob_get_clean();
        }

        $user->refresh();
        self::assertSame(ExitCode::OK, $exitCode);
        self::assertSame(self::ISSUER, $user->ssoIssuer);
        self::assertStringContainsString('(' . self::ISSUER . ').', $output);
        self::assertStringNotContainsString(self::ISSUER . '/', $output);
    }

    /**
     * Issuer и subject сравниваются побайтно, включая регистр и завершающие пробелы.
     */
    public function testIdentityPairUsesExactBinarySemantics(): void
    {
        $upper = $this->createUser(['username' => 'upper-subject']);
        $lower = $this->createUser(['username' => 'lower-subject']);
        $trailing = $this->createUser(['username' => 'trailing-subject']);
        $otherIssuer = $this->createUser(['username' => 'other-issuer']);
        $service = new SsoUserLinker();

        $service->prelink('upper-subject', self::ISSUER, 'ABC');
        $service->prelink('lower-subject', self::ISSUER, 'abc');
        $service->prelink('trailing-subject', self::ISSUER, 'ABC ');
        $service->prelink('other-issuer', 'https://other-sso.example.test', 'ABC');

        self::assertSame(
            $upper->id,
            $service->link(['iss' => self::ISSUER, 'sub' => 'ABC'])->id
        );
        self::assertSame(
            $lower->id,
            $service->link(['iss' => self::ISSUER, 'sub' => 'abc'])->id
        );
        self::assertSame(
            $trailing->id,
            $service->link(['iss' => self::ISSUER, 'sub' => 'ABC '])->id
        );
        self::assertSame(
            $otherIssuer->id,
            $service->link(['iss' => 'https://other-sso.example.test', 'sub' => 'ABC'])->id
        );
    }

    /**
     * Совпадающий subject от другого issuer не может использовать существующую привязку.
     */
    public function testRejectsSubjectFromDifferentIssuer(): void
    {
        $user = $this->createUser(['username' => 'issuer-bound']);
        $service = new SsoUserLinker();
        $service->prelink('issuer-bound', self::ISSUER, 'shared-subject');

        $this->expectException(SsoUserLinkException::class);
        $service->link([
            'iss' => 'https://attacker.example.test',
            'sub' => 'shared-subject',
        ]);
    }

    /**
     * Миграция закрепляет точные бинарные значения и уникальность полной identity pair.
     */
    public function testSchemaUsesBinaryCompositeIdentityAndCompletePairConstraint(): void
    {
        $columns = Yii::$app->db->createCommand(
            <<<'SQL'
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'user'
  AND COLUMN_NAME IN ('ssoIssuer', 'ssoSubject')
ORDER BY COLUMN_NAME
SQL
        )->queryAll();
        $indexColumns = Yii::$app->db->createCommand(
            <<<'SQL'
SELECT COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'user'
  AND INDEX_NAME = 'ux_user_ssoIdentity'
  AND NON_UNIQUE = 0
ORDER BY SEQ_IN_INDEX
SQL
        )->queryColumn();
        $checkCount = Yii::$app->db->createCommand(
            <<<'SQL'
SELECT COUNT(*)
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'user'
  AND CONSTRAINT_NAME = 'ck_user_ssoIdentity_complete'
  AND CONSTRAINT_TYPE = 'CHECK'
SQL
        )->queryScalar();

        self::assertSame([
            [
                'COLUMN_NAME' => 'ssoIssuer',
                'DATA_TYPE' => 'varbinary',
                'CHARACTER_SET_NAME' => null,
            ],
            [
                'COLUMN_NAME' => 'ssoSubject',
                'DATA_TYPE' => 'varbinary',
                'CHARACTER_SET_NAME' => null,
            ],
        ], $columns);
        self::assertSame(['ssoIssuer', 'ssoSubject'], $indexColumns);
        self::assertSame('1', (string) $checkCount);
    }

    /**
     * Обязательные строковые claims должны присутствовать и быть непустыми.
     */
    #[DataProvider('invalidRequiredClaimsProvider')]
    public function testRejectsMissingOrEmptyRequiredClaims(array $claims): void
    {
        $this->expectException(SsoUserLinkException::class);

        (new SsoUserLinker())->link($claims);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidRequiredClaimsProvider(): iterable
    {
        yield 'missing sub' => [[
            'iss' => self::ISSUER,
        ]];
        yield 'blank sub' => [[
            'iss' => self::ISSUER,
            'sub' => '',
        ]];
        yield 'missing issuer' => [[
            'sub' => 'subject',
        ]];
        yield 'blank issuer' => [[
            'iss' => '  ',
            'sub' => 'subject',
        ]];
    }
}
