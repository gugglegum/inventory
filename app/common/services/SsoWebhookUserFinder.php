<?php

declare(strict_types=1);

namespace common\services;

use common\models\User;
use Yii;
use yii\web\HttpException;

/**
 * Ищет заблокированного для обновления пользователя по configured issuer и точному sub.
 */
final class SsoWebhookUserFinder
{
    public function findForUpdate(string $subject): ?User
    {
        $issuer = Yii::$app->params['oidc']['issuer'] ?? null;
        if (!is_string($issuer) || $issuer === '') {
            throw new HttpException(503, 'OIDC issuer is not configured.');
        }

        try {
            $issuer = OidcConfiguration::canonicalizeIssuer($issuer);
        } catch (OidcException $exception) {
            throw new HttpException(503, 'OIDC issuer is not configured.', previous: $exception);
        }

        $user = User::findBySql(
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

        return $user;
    }
}
