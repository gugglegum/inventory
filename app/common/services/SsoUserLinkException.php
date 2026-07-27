<?php

declare(strict_types=1);

namespace common\services;

/**
 * Безопасная для показа пользователю ошибка привязки учетной записи SSO.
 */
final class SsoUserLinkException extends \RuntimeException
{
}
