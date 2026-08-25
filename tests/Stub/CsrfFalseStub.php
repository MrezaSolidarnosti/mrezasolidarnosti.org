<?php

declare(strict_types=1);

namespace Solidarity\Tests\Stub;

use Skeletor\Core\Security\Csrf;

/**
 * Always-failing CSRF stub, for asserting that a rejected token is handled.
 *
 * See CsrfTrueStub for why the constructor is overridden.
 */
class CsrfFalseStub extends Csrf
{
    public function __construct() {}

    public function validate(array $requestData = [], string $tokenName = self::TOKEN_NAME): bool
    {
        return false;
    }
}
