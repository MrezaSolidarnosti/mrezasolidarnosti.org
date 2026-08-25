<?php

declare(strict_types=1);

namespace Solidarity\Tests\Stub;

use Skeletor\Core\Security\Csrf;

/**
 * A working CSRF service backed by $_SESSION.
 *
 * The frontend harness seeds $_SESSION[Csrf::TOKEN_NAME] directly — that is what the real
 * request cycle ends up reading too, since Laminas' default session storage proxies the
 * superglobal. Skeletor's Csrf goes through the session manager instead, which the harness has
 * no reason to build, so this reads and writes $_SESSION while keeping the real semantics the
 * tests depend on: a token is good for exactly one request, because validate() rotates it on
 * success and leaves it alone on failure.
 *
 * Distinct from CsrfTrueStub/CsrfFalseStub, which force an answer; this one actually checks.
 */
class SessionCsrfStub extends Csrf
{
    public function __construct() {}

    public function generateToken(string $tokenName = self::TOKEN_NAME): string
    {
        return $_SESSION[$tokenName] = bin2hex(random_bytes(32));
    }

    public function getToken(string $tokenName = self::TOKEN_NAME): string
    {
        $existing = $_SESSION[$tokenName] ?? null;

        if (!is_string($existing) || $existing === '') {
            return $this->generateToken($tokenName);
        }

        return $existing;
    }

    public function validate(array $requestData = [], string $tokenName = self::TOKEN_NAME): bool
    {
        $expected = $_SESSION[$tokenName] ?? null;
        $submitted = $requestData[$tokenName] ?? null;

        if (!is_string($expected) || $expected === '' || !is_string($submitted)) {
            return false;
        }

        if (!hash_equals($expected, $submitted)) {
            return false;
        }

        $this->generateToken($tokenName);

        return true;
    }
}
