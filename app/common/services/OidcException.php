<?php

declare(strict_types=1);

namespace common\services;

use RuntimeException;

/**
 * Базовая ошибка протокольного OIDC-слоя.
 *
 * Сообщения исключения намеренно не содержат authorization code, client secret,
 * токены или тела HTTP-ответов.
 */
final class OidcException extends RuntimeException
{
}
