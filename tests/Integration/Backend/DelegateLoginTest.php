<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Skeletor\Core\Mailer\Service\MailerInterface;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Login\Exception\InvalidCredentials;
use Skeletor\Login\Filter\ResetPassword;
use Skeletor\Login\Provider\ProviderInterface;
use Skeletor\Login\Repository\ForgotPasswordRepository;
use Skeletor\Login\Service\Login;
use Skeletor\Login\Service\MagicLinkService;
use Solidarity\Backend\Controller\DelegateLoginController;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Tamtamchik\SimpleFlash\Flash;

/**
 * The delegate front door.
 *
 * There is no password path — a delegate gets in by asking for a link and following it, and
 * nothing else. So this controller decides both who may ask (the account-status gate) and
 * what a followed link establishes (the session every later permission check reads).
 *
 * The token mechanics themselves live in Skeletor's MagicLinkService and are not retested
 * here; what is app-specific is the gate in front of it and the session behind it. The
 * Login service is real rather than mocked, so the session assertions are the actual keys
 * AuthMiddleware and AccessControlTest key off, not a stand-in for them.
 */
#[CoversClass(DelegateLoginController::class)]
final class DelegateLoginTest extends IntegrationTestCase
{
    /**
     * The controller's own constant is '/admin/login/delegate/magicLinkForm/', but
     * Controller::redirect() rewrites the literal 'admin/' segment to the configured
     * adminPath — which is empty here, as in dev — so this is what actually lands in the
     * Location header.
     */
    private const FORM_PATH = '/login/delegate/magicLinkForm/';

    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    private ArrayStorage $storage;

    /** @var list<array{string, string}> calls to MagicLinkService::requestMagicLink */
    private array $linksRequested = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION = ['flash_messages' => []];
        $this->storage = new ArrayStorage();
        $this->linksRequested = [];
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }

        parent::tearDown();
    }

    // ---- who may ask for a link ---------------------------------------------------

    public function testAVerifiedDelegateIsSentALink(): void
    {
        $delegate = $this->createDelegate(Delegate::STATUS_VERIFIED);

        $response = $this->requestLink($delegate->email);

        self::assertSame([[$delegate->email, 'delegate']], $this->linksRequested);
        self::assertStringEndsWith(self::FORM_PATH . '?sent', $response->getHeaderLine('Location'));
        self::assertContains('Link za login je poslat. Proverite mail.', $this->flash('success'));
    }

    public function testAnUnverifiedDelegateIsRefusedBeforeAnyLinkIsSent(): void
    {
        // Delegate::isActive() is true only for STATUS_VERIFIED, and this is the one place
        // that consults it on the way in. A delegate who signed up but has not been approved
        // must not be able to mail themselves a working key to the dashboard.
        $delegate = $this->createDelegate(Delegate::STATUS_NEW);

        $this->requestLink($delegate->email);

        self::assertSame([], $this->linksRequested, 'no link may be issued for an inactive account');
        self::assertContains('Vaš nalog nije aktivan. Kontaktirajte administratora.', $this->flash('error'));
    }

    public function testADelegateFlaggedAsAProblemIsAlsoRefused(): void
    {
        // STATUS_PROBLEM is how an admin parks an account they are unsure about; it has to
        // deny access rather than merely annotate it.
        $delegate = $this->createDelegate(Delegate::STATUS_PROBLEM);

        $this->requestLink($delegate->email);

        self::assertSame([], $this->linksRequested);
    }

    public function testAnAddressThatIsNotADelegateIsReportedAsNotFound(): void
    {
        $this->requestLink('nobody@example.com');

        self::assertSame([], $this->linksRequested);
        self::assertContains('Email not found in system.', $this->flash('error'));
    }

    public function testAMalformedAddressIsRejectedWithoutLookingAnythingUp(): void
    {
        $this->requestLink('not-an-email');

        self::assertSame([], $this->linksRequested);
        self::assertContains('Unesite validnu email adresu', $this->flash('error'));
    }

    public function testAMissingAddressIsRejectedRatherThanTreatedAsEmpty(): void
    {
        $response = $this->requestLink(null);

        self::assertSame([], $this->linksRequested);
        self::assertSame(302, $response->getStatusCode());
    }

    // ---- what following a link establishes -------------------------------------------

    public function testAValidTokenLogsTheDelegateInAsADelegate(): void
    {
        // loggedInEntityType is what AuthMiddleware uses to pick the repository, and what
        // every delegate-scoped controller branches on. Getting 'user' here would hand a
        // delegate the staff view of the dashboard.
        $delegate = $this->createDelegate();

        $response = $this->verify('a-valid-token', $delegate);

        self::assertSame($delegate->getId(), $this->storage->offsetGet('loggedIn'));
        self::assertSame('delegate', $this->storage->offsetGet('loggedInEntityType'));
        self::assertSame($delegate->getAuthRole(), $this->storage->offsetGet('loggedInRole'));
        self::assertSame($delegate->email, $this->storage->offsetGet('loggedInEmail'));
        self::assertStringEndsWith('/beneficiary/view/', $response->getHeaderLine('Location'));
    }

    public function testARejectedTokenEstablishesNoSessionAtAll(): void
    {
        // The important half of a login test. A failed verify must leave nothing behind —
        // a half-written session is worse than no session, because AuthMiddleware only
        // checks that 'loggedIn' is truthy.
        $this->verifyFailing(new InvalidCredentials('Invalid magic link'));

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertNull($this->storage->offsetGet('loggedInEntityType'));
        self::assertContains('Invalid magic link', $this->flash('error'));
    }

    public function testARequestWithNoTokenNeverReachesTheAuthenticator(): void
    {
        $controller = $this->controller();
        $controller->setRequest(new ServerRequest('GET', '/login/delegate/verifyMagicLink/'));

        $response = $controller->verifyMagicLink();

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertStringEndsWith(self::FORM_PATH, $response->getHeaderLine('Location'));
    }

    public function testAnUnexpectedFailureIsNotShownToTheVisitor(): void
    {
        // The generic arm exists so a database error on the login page does not print its
        // message onto a public form. Worth pinning: the specific arm above deliberately
        // does echo its message, so the distinction is easy to collapse by accident.
        $this->verifyFailing(new \RuntimeException('SQLSTATE[HY000] connection refused'));

        self::assertContains('An error occurred. Please try again.', $this->flash('error'));
        self::assertNotContains('SQLSTATE[HY000] connection refused', $this->flash('error'));
    }

    public function testEveryFailedVerifySendsThemBackToTheFormRatherThanOnwards(): void
    {
        $response = $this->verifyFailing(new InvalidCredentials('nope'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringEndsWith(self::FORM_PATH, $response->getHeaderLine('Location'));
    }

    // ---- already signed in ---------------------------------------------------------

    public function testADelegateWhoIsAlreadySignedInIsSentStraightToTheirWork(): void
    {
        // Login stores redirectPath, so this branch has something to redirect to. It is also
        // the only branch of magicLinkForm() that can be driven here — the other one renders
        // a template, and Controller::respond() turns a template failure into a var_dump.
        $this->storage->offsetSet('loggedIn', 7);
        $this->storage->offsetSet('redirectPath', '/beneficiary/view/');

        $controller = $this->controller();
        $controller->setRequest(new ServerRequest('GET', self::FORM_PATH));

        $response = $controller->magicLinkForm();

        self::assertSame(302, $response->getStatusCode());
        self::assertStringEndsWith('/beneficiary/view/', $response->getHeaderLine('Location'));
    }

    // ---- driving the endpoints ---------------------------------------------------------

    private function requestLink(?string $email): ResponseInterface
    {
        $controller = $this->controller();
        $controller->setRequest(
            (new ServerRequest('POST', '/login/delegate/requestMagicLink/'))
                ->withParsedBody($email === null ? [] : ['email' => $email]),
        );

        return $controller->requestMagicLink();
    }

    private function verify(string $token, Delegate $resolvesTo): ResponseInterface
    {
        $authenticator = $this->createStub(AuthenticatorRegistry::class);
        $authenticator->method('authenticate')->willReturn($resolvesTo);

        return $this->runVerify($token, $authenticator);
    }

    private function verifyFailing(\Throwable $failure): ResponseInterface
    {
        $authenticator = $this->createStub(AuthenticatorRegistry::class);
        $authenticator->method('authenticate')->willThrowException($failure);

        return $this->runVerify('a-token', $authenticator);
    }

    private function runVerify(string $token, AuthenticatorRegistry $authenticator): ResponseInterface
    {
        $controller = $this->controller($authenticator);
        $controller->setRequest(
            (new ServerRequest('GET', '/login/delegate/verifyMagicLink/'))->withAttribute('token', $token),
        );

        return $controller->verifyMagicLink();
    }

    // ---- collaborators --------------------------------------------------------------------

    private function controller(?AuthenticatorRegistry $authenticator = null): DelegateLoginController
    {
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($this->storage);

        // Real Login, sharing the storage above, so "was a session established" is answered
        // by the keys that actually get written rather than by a recorded method call.
        $login = new Login(
            $this->createStub(ProviderInterface::class),
            $session,
            $this->createStub(MailerInterface::class),
            $this->createStub(ForgotPasswordRepository::class),
        );

        $magicLink = $this->createStub(MagicLinkService::class);
        // requestMagicLink() returns the token it issued, so the callback has to hand one
        // back — a void callback fails the stub's return type, not the assertion.
        $magicLink->method('requestMagicLink')->willReturnCallback(
            function (string $email, string $entityType = 'user', bool $sendEmail = true): string {
                $this->linksRequested[] = [$email, $entityType];

                return 'issued-token';
            },
        );

        $entityRegistry = $this->createStub(EntityRegistry::class);
        // A real repository: the status gate has to be exercised against a stored delegate,
        // and findByEmail() throws NotFoundException for an unknown address rather than
        // returning null — which is the arm that reports "Email not found in system."
        $entityRegistry->method('getRepository')->willReturn(new DelegateRepository($this->em()));

        return new DelegateLoginController(
            $login,
            $session,
            new Config(['adminPath' => '', 'adminUrl' => '']),
            new Flash(),
            new Engine(),
            $this->createStub(ResetPassword::class),
            $this->createStub(ForgotPasswordRepository::class),
            $magicLink,
            $authenticator ?? $this->createStub(AuthenticatorRegistry::class),
            $entityRegistry,
        );
    }

    /** @return string[] */
    private function flash(string $type): array
    {
        return $_SESSION['flash_messages'][$type] ?? [];
    }
}
