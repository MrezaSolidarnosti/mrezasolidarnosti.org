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
use Skeletor\Login\Service\Login;
use Solidarity\Backend\Action\Logout;
use Tamtamchik\SimpleFlash\Flash;

#[CoversClass(Logout::class)]
final class LogoutTest extends TestCase
{
    public function testDelegateLogoutRedirectsToDelegateLogin(): void
    {
        self::assertSame(
            'http://admin/login/delegate/magicLinkForm/',
            $this->invoke('delegate'),
        );
    }

    public function testUserLogoutRedirectsToUserLogin(): void
    {
        self::assertSame(
            'http://admin/login/user/magicLinkForm/',
            $this->invoke('user'),
        );
    }

    public function testLoggedOutSessionRedirectsToUserLogin(): void
    {
        self::assertSame(
            'http://admin/login/user/magicLinkForm/',
            $this->invoke(null),
        );
    }

    private function invoke(?string $entityType): string
    {
        $storage = new ArrayStorage(['loggedInEntityType' => $entityType]);
        $session = $this->createStub(ManagerInterface::class);
        $session->method('getStorage')->willReturn($storage);

        $action = new Logout(
            new NullLogger(),
            new Config(['adminUrl' => 'http://admin']),
            new Engine(),
            $session,
            $this->createStub(Flash::class),
            $this->createStub(Login::class),
        );

        $response = $action(new ServerRequest('GET', '/'), new Response());

        self::assertSame(302, $response->getStatusCode());

        return $response->getHeaderLine('Location');
    }
}
