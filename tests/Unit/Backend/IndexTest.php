<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use Laminas\Session\ManagerInterface;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Solidarity\Backend\Action\Index;
use Tamtamchik\SimpleFlash\Flash;

#[CoversClass(Index::class)]
final class IndexTest extends TestCase
{
    public function testRedirectsToUserLoginWhenNotLoggedIn(): void
    {
        $location = $this->invoke(loggedIn: null, entityType: null);

        self::assertSame('http://admin/login/user/magicLinkForm/', $location);
    }

    public function testRedirectsLoggedInUserToUserView(): void
    {
        $location = $this->invoke(loggedIn: 5, entityType: 'user');

        self::assertSame('http://admin/user/view/', $location);
    }

    public function testRedirectsLoggedInDelegateToBeneficiaryView(): void
    {
        // The name was always right and the assertion was not: this pointed at
        // /educator/view/, a retired section with no permission mapping, so the delegate was
        // denied and bounced on with a spurious error. It has to match Delegate::
        // getRedirectPath(), which is where verifyMagicLink() already sends them.
        $location = $this->invoke(loggedIn: 7, entityType: 'delegate');

        self::assertSame('http://admin/beneficiary/view/', $location);
    }

    private function invoke(mixed $loggedIn, ?string $entityType): string
    {
        $storage = new ArrayStorage(['loggedIn' => $loggedIn, 'loggedInEntityType' => $entityType]);
        $session = $this->createStub(ManagerInterface::class);
        $session->method('getStorage')->willReturn($storage);

        $action = new Index(
            new NullLogger(),
            new Config(['adminUrl' => 'http://admin']),
            new Engine(),
            $session,
            $this->createStub(Flash::class),
        );

        $response = $action(new ServerRequest('GET', '/'), new Response());

        self::assertSame(302, $response->getStatusCode());

        return $response->getHeaderLine('Location');
    }
}
