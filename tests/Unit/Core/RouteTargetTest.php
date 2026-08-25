<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Controller\Controller;

/**
 * Every route points at something that exists.
 *
 * Nothing else in the suite loads the route tables, and this is the failure they produce:
 * `/educatorImport/` and `/transactionImport/` outlived their controllers and sat as live
 * routes referencing deleted classes. Permission-denied roles never noticed; an **admin**
 * got a 500 instead of a 404, because the class only resolves once the middleware lets the
 * request through. That was found by reading, not by a failing test.
 *
 * Static, deliberately: it loads the route arrays and reflects on the targets. Booting the
 * container would catch more (DI wiring, middleware) but needs Redis, config-local.php and
 * the APPLICATION env var, which is a great deal of setup for a handful of assertions.
 */
#[CoversNothing]
final class RouteTargetTest extends TestCase
{
    #[DataProvider('routes')]
    public function testTheRouteTargetStillExists(string $app, string $path, string $target): void
    {
        // A route naming a class that has been deleted is a 500 for whoever is allowed
        // through to it, and invisible to everyone else.
        self::assertTrue(
            class_exists($target),
            sprintf('%s route %s points at %s, which does not exist', $app, $path, $target),
        );
    }

    #[DataProvider('routes')]
    public function testTheRouteTargetCanActuallyBeDispatchedTo(string $app, string $path, string $target): void
    {
        if (!class_exists($target)) {
            self::markTestSkipped('covered by testTheRouteTargetStillExists');
        }

        $reflection = new \ReflectionClass($target);
        self::assertFalse($reflection->isAbstract(), $target . ' is abstract');

        // Two shapes are dispatchable: a Controller, which resolves {action} to a method at
        // runtime, and a single-action class with __invoke.
        $dispatchable = $reflection->isSubclassOf(Controller::class) || $reflection->hasMethod('__invoke');

        self::assertTrue(
            $dispatchable,
            sprintf('%s route %s targets %s, which is neither a Controller nor invokable', $app, $path, $target),
        );
    }

    #[DataProvider('singleActionRoutes')]
    public function testARouteWithoutAnActionPlaceholderIsSingleAction(string $app, string $path, string $target): void
    {
        if (!class_exists($target)) {
            self::markTestSkipped('covered by testTheRouteTargetStillExists');
        }

        // No {action} in the path means nothing tells the controller which method to run,
        // so the target has to be invokable on its own.
        self::assertTrue(
            (new \ReflectionClass($target))->hasMethod('__invoke'),
            sprintf('%s route %s has no {action} placeholder, so %s needs __invoke', $app, $path, $target),
        );
    }

    public function testBothRouteTablesWereActuallyRead(): void
    {
        // The provider reads files by path; if either moves, every case above silently
        // disappears and the suite stays green.
        // array_values, because array_unique keeps the original keys and the comparison
        // would then be against [0 => 'backend', 22 => 'frontend'].
        $apps = array_values(array_unique(array_column(self::routes(), 0)));

        self::assertEqualsCanonicalizing(['backend', 'frontend'], $apps);
    }

    /**
     * Routes whose path carries no {action}, so the target must be invokable on its own.
     *
     * A separate provider rather than a skip inside the test: filtering at runtime reported
     * every {action} route as skipped, which is 18 lines of noise describing nothing wrong.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function singleActionRoutes(): array
    {
        return array_filter(self::routes(), static fn (array $case): bool => !str_contains($case[1], '{action}'));
    }

    /**
     * Every [app, path, target class] across both route tables.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function routes(): array
    {
        $cases = [];
        foreach (['backend', 'frontend'] as $app) {
            $file = APP_PATH . '/config/' . $app . '/routes.php';
            if (!is_file($file)) {
                continue;
            }
            foreach (require $file as $route) {
                // [ [methods], path, target ]; a target may be a class-string or
                // [Class => method], which only the framework's own routes use.
                $target = is_array($route[2]) ? array_key_first($route[2]) : $route[2];
                if (!is_string($target)) {
                    continue;
                }
                $cases[$app . ' ' . $route[1]] = [$app, $route[1], $target];
            }
        }

        return $cases;
    }
}
