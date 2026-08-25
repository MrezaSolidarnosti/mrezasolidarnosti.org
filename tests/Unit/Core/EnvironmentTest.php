<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Core\Environment;

/**
 * Two environment variables decide a surprising amount.
 *
 * APPLICATION_ENV gates whether Mailer sends through MailerSend or diverts everything to
 * SMTP, so isProduction() returning true by accident means real mail to real donors from
 * a dev box. APPLICATION picks the route table, the ACL and the DI wiring, so a wrong
 * answer serves the admin app on the public vhost.
 *
 * Both must therefore be *strict*: only the exact value counts, and an unset variable has
 * to be false rather than a default. These tests exist mostly to pin that strictness.
 */
#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    private string|false $env;
    private string|false $application;

    protected function setUp(): void
    {
        $this->env = getenv('APPLICATION_ENV');
        $this->application = getenv('APPLICATION');
    }

    protected function tearDown(): void
    {
        $this->restore('APPLICATION_ENV', $this->env);
        $this->restore('APPLICATION', $this->application);
    }

    // ---- APPLICATION_ENV -----------------------------------------------------

    public function testProductionIsRecognised(): void
    {
        putenv('APPLICATION_ENV=production');

        self::assertTrue(Environment::isProduction());
        self::assertSame('production', Environment::name());
    }

    public function testTheValueIsCaseInsensitive(): void
    {
        // nginx config and crontabs are hand-written; "Production" must not silently
        // become a non-production environment that mails nobody.
        putenv('APPLICATION_ENV=PRODUCTION');

        self::assertTrue(Environment::isProduction());
    }

    public function testDevelopmentIsNotProduction(): void
    {
        putenv('APPLICATION_ENV=development');

        self::assertFalse(Environment::isProduction());
    }

    public function testAnUnsetEnvironmentIsNotProduction(): void
    {
        // Cron does not inherit the web server's variables. Defaulting to production here
        // would mean an unconfigured cron mailing live donors from a staging box.
        putenv('APPLICATION_ENV');

        self::assertFalse(Environment::isProduction());
        self::assertSame('', Environment::name());
    }

    public function testAValueThatMerelyContainsProductionIsNotProduction(): void
    {
        // "pre-production" is the classic way to accidentally go live.
        putenv('APPLICATION_ENV=pre-production');

        self::assertFalse(Environment::isProduction());
    }

    // ---- APPLICATION -----------------------------------------------------------

    public function testTheFrontendAndBackendAppsAreDistinguished(): void
    {
        putenv('APPLICATION=frontend');
        self::assertTrue(Environment::isFrontend());
        self::assertFalse(Environment::isBackend());

        putenv('APPLICATION=backend');
        self::assertTrue(Environment::isBackend());
        self::assertFalse(Environment::isFrontend());
    }

    public function testTheApplicationIsCaseInsensitive(): void
    {
        putenv('APPLICATION=Backend');

        self::assertTrue(Environment::isBackend());
        self::assertSame('backend', Environment::application());
    }

    public function testAnUnsetApplicationIsNeitherApp(): void
    {
        // A CLI run with no APPLICATION set must not be mistaken for the backend and
        // pick up its ACL and route table.
        putenv('APPLICATION');

        self::assertFalse(Environment::isFrontend());
        self::assertFalse(Environment::isBackend());
        self::assertSame('', Environment::application());
    }

    // ---- the values are read every time ----------------------------------------

    public function testTheValueIsReReadRatherThanCached(): void
    {
        // The docblock promises this so tests can override with putenv(); caching in a
        // static would also break the CLI entry points, which set it after autoload.
        putenv('APPLICATION_ENV=development');
        self::assertFalse(Environment::isProduction());

        putenv('APPLICATION_ENV=production');
        self::assertTrue(Environment::isProduction());
    }

    private function restore(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);

            return;
        }
        putenv($name . '=' . $value);
    }
}
