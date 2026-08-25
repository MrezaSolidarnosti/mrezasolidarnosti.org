<?php

declare(strict_types=1);

namespace Solidarity\Tests\Stub;

use Skeletor\Core\Security\Csrf;

/**
 * Always-passing CSRF stub.
 *
 * Skeletor's Csrf is a normal service now, so this could equally be a PHPUnit mock — the stub
 * survives only because it keeps the call sites short. The constructor is overridden so the stub
 * needs no session manager; only validate() is exercised, and it never touches session storage.
 */
class CsrfTrueStub extends Csrf
{
    public function __construct() {}

    public function validate(array $requestData = [], string $tokenName = self::TOKEN_NAME): bool
    {
        return true;
    }
}
