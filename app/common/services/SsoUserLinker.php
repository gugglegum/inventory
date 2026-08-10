<?php

declare(strict_types=1);

namespace common\services;

use common\models\User;
use JsonException;
use yii\db\Exception;

/**
 * Находит заранее привязанного пользователя Stockhub по проверенной паре OIDC issuer/subject.
 *
 * Автоматическая привязка по email намеренно запрещена: SSO profile email нельзя
 * использовать как административное доказательство владения локальной учетной записью.
 */
final class SsoUserLinker
{
    private const string DATABASE_ERROR_MESSAGE = 'Не удалось безопасно привязать учетную запись SSO.';

    /**
     * Находит активную локальную учетную запись только по заранее сохраненной паре issuer/subject.
     *
     * @param array<array-key, mixed> $claims Проверенные claims из ID token.
     * @throws SsoUserLinkException
     */
    public function link(array $claims): User
    {
        $issuer = $this->requireIssuerClaim($claims);
        $subject = $this->requireSubjectClaim($claims);

        try {
            $claimsJson = json_encode(
                $claims,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException) {
            throw new SsoUserLinkException('SSO вернул некорректные данные пользователя.');
        }

        try {
            /** @var User $user */
            $user = User::getDb()->transaction(
                function () use ($issuer, $subject, $claimsJson): User {
                    $user = $this->findActiveByIdentityForUpdate($issuer, $subject);
                    if ($user === null) {
                        throw new SsoUserLinkException(
                            'Активная учетная запись Stockhub не привязана к этому пользователю SSO.'
                        );
                    }

                    $user->ssoClaims = $claimsJson;
                    $user->lastSsoLoginAt = time();
                    $user->updateAttributes([
                        'ssoClaims',
                        'lastSsoLoginAt',
                    ]);

                    return $user;
                }
            );

            return $user;
        } catch (SsoUserLinkException $exception) {
            throw $exception;
        } catch (Exception) {
            throw new SsoUserLinkException(self::DATABASE_ERROR_MESSAGE);
        }
    }

    /**
     * @param array<array-key, mixed> $claims
     * @throws SsoUserLinkException
     */
    private function requireIssuerClaim(array $claims): string
    {
        $value = $claims['iss'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new SsoUserLinkException('SSO не вернул обязательный claim iss.');
        }
        if (strlen($value) > 255) {
            throw new SsoUserLinkException('SSO вернул слишком длинный claim iss.');
        }

        return $value;
    }

    /**
     * OIDC subject является регистрозависимой строкой; пробелы не нормализуются.
     *
     * @param array<array-key, mixed> $claims
     * @throws SsoUserLinkException
     */
    private function requireSubjectClaim(array $claims): string
    {
        $value = $claims['sub'] ?? null;
        if (!is_string($value) || $value === '') {
            throw new SsoUserLinkException('SSO не вернул обязательный claim sub.');
        }
        if (strlen($value) > 255) {
            throw new SsoUserLinkException('SSO вернул слишком длинный claim sub.');
        }

        return $value;
    }

    /**
     * Блокирующее чтение гарантирует, что параллельная привязка того же пользователя
     * будет увидена до записи текущей транзакции.
     */
    private function findActiveByIdentityForUpdate(string $issuer, string $subject): ?User
    {
        return User::findBySql(
            <<<'SQL'
SELECT *
FROM {{%user}}
WHERE [[ssoIssuer]] = :issuer
  AND [[ssoSubject]] = :subject
  AND [[status]] = :status
LIMIT 1
FOR UPDATE
SQL,
            [
                ':issuer' => $issuer,
                ':subject' => $subject,
                ':status' => User::STATUS_ACTIVE,
            ]
        )->one();
    }

    /**
     * Административно и идемпотентно связывает активного пользователя с OIDC issuer/subject.
     *
     * @throws SsoUserLinkException
     */
    public function prelink(string $identifier, string $issuer, string $subject): User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new SsoUserLinkException('Нужно указать username или email пользователя Stockhub.');
        }

        try {
            $issuer = OidcConfiguration::canonicalizeIssuer($issuer);
        } catch (OidcException) {
            throw new SsoUserLinkException('OIDC issuer должен быть корректным HTTP(S) URL.');
        }

        if (strlen($issuer) > 255) {
            throw new SsoUserLinkException('OIDC issuer должен содержать от 1 до 255 байт.');
        }
        if ($subject === '' || strlen($subject) > 255) {
            throw new SsoUserLinkException('OIDC subject должен содержать от 1 до 255 байт.');
        }

        try {
            /** @var User $user */
            $user = User::getDb()->transaction(function () use ($identifier, $issuer, $subject): User {
                $users = $this->findActiveByIdentifierForUpdate($identifier);
                if (count($users) !== 1) {
                    throw new SsoUserLinkException(
                        count($users) === 0
                            ? 'Активный пользователь Stockhub не найден.'
                            : 'Username или email неоднозначно определяет пользователя Stockhub.'
                    );
                }

                $user = $users[0];
                $identityOwner = $this->findAnyByIdentityForUpdate($issuer, $subject);

                if ($identityOwner !== null && (int) $identityOwner->id !== (int) $user->id) {
                    throw new SsoUserLinkException(
                        'Эта учетная запись OIDC уже привязана к другому пользователю Stockhub.'
                    );
                }

                if (
                    $user->ssoIssuer !== null
                    && (
                        $user->ssoIssuer !== $issuer
                        || $user->ssoSubject !== $subject
                    )
                ) {
                    throw new SsoUserLinkException(
                        'Учетная запись Stockhub уже привязана к другой учетной записи OIDC.'
                    );
                }

                if ($user->ssoIssuer === null || $user->ssoSubject === null) {
                    $user->updateAttributes([
                        'ssoIssuer' => $issuer,
                        'ssoSubject' => $subject,
                    ]);
                }

                return $user;
            });

            return $user;
        } catch (SsoUserLinkException $exception) {
            throw $exception;
        } catch (Exception) {
            throw new SsoUserLinkException(self::DATABASE_ERROR_MESSAGE);
        }
    }

    /**
     * @return list<User>
     */
    private function findActiveByIdentifierForUpdate(string $identifier): array
    {
        $users = User::findBySql(
            <<<'SQL'
SELECT *
FROM {{%user}}
WHERE [[status]] = :status
  AND ([[username]] = :identifier OR [[email]] = :identifier)
FOR UPDATE
SQL,
            [
                ':identifier' => $identifier,
                ':status' => User::STATUS_ACTIVE,
            ]
        )->all();

        /** @var list<User> $users */
        return $users;
    }

    private function findAnyByIdentityForUpdate(string $issuer, string $subject): ?User
    {
        return User::findBySql(
            <<<'SQL'
SELECT *
FROM {{%user}}
WHERE [[ssoIssuer]] = :issuer
  AND [[ssoSubject]] = :subject
LIMIT 1
FOR UPDATE
SQL,
            [
                ':issuer' => $issuer,
                ':subject' => $subject,
            ]
        )->one();
    }
}
