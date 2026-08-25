<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Skeletor\Login\Service\Login;
use Skeletor\Login\Provider\ProviderInterface;
use Skeletor\Login\Repository\ForgotPasswordRepository;
use Skeletor\Core\Mailer\Service\MailerInterface;
use Solidarity\Frontend\Action\Donor\Logout;
use Tamtamchik\SimpleFlash\Flash;

/**
 * Signing a donor out.
 *
 * Short enough to look self-evident, which is why it is worth pinning: the session keys it
 * has to clear are written by Login::login(), in a different package, and the two lists have
 * to stay in step. A key left behind is a donor who still looks logged in to
 * Session::isDonor() — the gate every donor-only endpoint checks.
 *
 * The Login service is real here, sharing one storage with the test, so this asserts the
 * keys that actually get cleared rather than that a method was called.
 */
#[CoversClass(Logout::class)]
final class LogoutTest extends FrontendActionTestCase
{
    /** Written by Login::login(); logout() has to remove each one. */
    private const SESSION_KEYS = [
        'loggedIn',
        'loggedInRole',
        'loggedInEmail',
        'loggedInFirstName',
        'loggedInLastName',
        'redirectPath',
    ];

    private ArrayStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new ArrayStorage();
    }

    public function testSigningOutClearsEverythingTheSessionKnewAboutTheDonor(): void
    {
        foreach (self::SESSION_KEYS as $key) {
            $this->storage->offsetSet($key, 'set-at-login');
        }

        $this->logout();

        foreach (self::SESSION_KEYS as $key) {
            self::assertNull($this->storage->offsetGet($key), $key . ' survived logout');
        }
    }

    public function testTheDonorIsReturnedToTheHomepage(): void
    {
        $response = $this->logout();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $this->redirectPath($response));
    }

    public function testSigningOutIsConfirmedOnTheNextPage(): void
    {
        $this->logout();

        self::assertContains(Logout::LOGGED_OUT, $_SESSION['flash_messages']['success'] ?? []);
    }

    public function testSigningOutWhenNobodyIsSignedInIsHarmless(): void
    {
        // Reachable by a bookmark, a second tab, or a back button. logout() only unsets
        // keys, so there is nothing to fail on — but it must not 500 either.
        $response = $this->logout();

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->storage->offsetGet('loggedIn'));
    }

    private function logout(): ResponseInterface
    {
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($this->storage);

        $action = new Logout(
            $this->logger(),
            $this->config(),
            $this->engine(),
            new Login(
                $this->createStub(ProviderInterface::class),
                $session,
                $this->createStub(MailerInterface::class),
                $this->createStub(ForgotPasswordRepository::class),
            ),
            new Flash(),
        );

        return $action($this->post(), $this->emptyResponse());
    }
}
