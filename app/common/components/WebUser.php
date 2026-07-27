<?php

declare(strict_types=1);

namespace common\components;

/**
 * Web user component with deterministic remember-me cookie cleanup.
 *
 * Yii normally removes the identity cookie during logout only while
 * enableAutoLogin is enabled. Stockhub disables auto-login together with the
 * password fallback, so an old cookie would otherwise survive an explicit
 * logout and could become active again after the fallback is enabled.
 */
final class WebUser extends \yii\web\User
{
    /**
     * Logs the current user out and always expires the configured identity cookie.
     *
     * @param bool $destroySession Whether to destroy the whole session.
     */
    public function logout($destroySession = true): bool
    {
        $loggedOut = parent::logout($destroySession);
        $this->removeIdentityCookie();

        return $loggedOut;
    }
}
