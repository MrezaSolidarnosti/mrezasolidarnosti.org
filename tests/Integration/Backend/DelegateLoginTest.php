<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\ServerRequest;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Skeletor\Core\Config\Config;
use Skeletor\Core\Security\Authentication\PendingAuthentication;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\Authenticator\MagicLinkAuthenticator;
use Skeletor\Core\Security\Authenticator\PasswordAuthenticator;
use Skeletor\Core\Security\Csrf;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Core\Security\AuthPolicy;
use Skeletor\Core\Login\Repository\ForgotPasswordRepository;
use Skeletor\Core\Login\Repository\MagicLinkTokenRepository;
use Skeletor\Core\Login\Service\Login;
use Skeletor\Core\Login\Service\MagicLinkService;
use Skeletor\Core\Login\Service\TokenGenerator;
use Skeletor\Core\Login\Filter\ForgotPassword as ForgotPasswordFilter;
use Skeletor\Core\Login\Filter\ResetPassword;
use Skeletor\Core\Login\Validator\ForgotPassword as ForgotPasswordValidator;
use Skeletor\Core\Login\Validator\ResetPasswordLoose;
use Skeletor\User\Filter\Login as LoginFilter;
use Skeletor\User\Validator\Login as LoginValidator;
use Solidarity\Backend\Controller\LoginController;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Tamtamchik\SimpleFlash\Flash;

/**
 * The delegate front door.
 *
 * There is no password path — a delegate gets in by asking for a link and following it, and
 * nothing else. What used to be tested here was a solidarity-owned controller; the rules it
 * enforced now live in the framework, and this app keeps only the Serbian wording. So this
 * drives the real framework controller through the real MagicLinkService against a real
 * DelegateRepository: the account-status gate has moved, and the point of this file is that
 * it still holds from this entry point.
 *
 * Everything is real except the mailer and the session store, so "was a session established"
 * is answered by the keys AuthMiddleware actually reads.
 */
#[CoversClass(LoginController::class)]
final class DelegateLoginTest extends IntegrationTestCase
{
    private const FORM_PATH = '/login/delegate/magicLinkForm/';

    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    private SessionManager $session;
    private ArrayStorage $storage;
    private MagicLinkTokenRepository $tokens;
    private EntityRegistry $registry;

    /** @var list<array{email: string, displayName: string, loginUrl: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION = ['flash_messages' => []];
        $this->storage = new ArrayStorage();
        // A stub sharing the storage below, so the session assertions read the keys the app
        // actually writes rather than a recorded call. regenerateId()/destroy() are no-ops
        // here; what they do is the framework's own test to make.
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($this->storage);
        $this->session = $session;
        $this->sent = [];

        $this->tokens = new MagicLinkTokenRepository($this->em(), new \DateTime());
        $this->registry = new EntityRegistry();
        $this->registry->register('delegate', Delegate::class, new DelegateRepository($this->em()));
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

        self::assertCount(1, $this->sent);
        self::assertSame($delegate->email, $this->sent[0]['email']);
        self::assertStringContainsString('/login/delegate/verifyMagicLink/', $this->sent[0]['loginUrl']);
        self::assertStringEndsWith(self::FORM_PATH . '?sent', $response->getHeaderLine('Location'));
        self::assertContains(LoginController::MAGIC_LINK_SENT, $this->flash('success'));
    }

    public function testAnUnverifiedDelegateIsRefusedBeforeAnyLinkIsSent(): void
    {
        // Delegate::isActive() is true only for STATUS_VERIFIED, and MagicLinkService is now
        // the one place that consults it on the way in — for every entity type and every
        // entry point. A delegate who signed up but has not been approved must not be able to
        // mail themselves a working key to the dashboard.
        $delegate = $this->createDelegate(Delegate::STATUS_NEW);

        $this->requestLink($delegate->email);

        self::assertSame([], $this->sent, 'no link may be issued for an inactive account');
        self::assertContains(LoginController::LOGIN_ERROR_INACTIVE, $this->flash('error'));
    }

    public function testADelegateFlaggedAsAProblemIsAlsoRefused(): void
    {
        $delegate = $this->createDelegate(Delegate::STATUS_PROBLEM);

        $this->requestLink($delegate->email);

        self::assertSame([], $this->sent);
    }

    public function testAnAddressThatIsNotADelegateIsReportedAsNotFound(): void
    {
        $this->requestLink('nobody@example.com');

        self::assertSame([], $this->sent);
        self::assertContains(LoginController::LOGIN_ERROR_NO_EMAIL, $this->flash('error'));
    }

    public function testAMalformedAddressIsRejectedWithoutLookingAnythingUp(): void
    {
        $this->requestLink('not-an-email');

        self::assertSame([], $this->sent);
        self::assertContains(LoginController::INVALID_EMAIL, $this->flash('error'));
    }

    public function testARequestWithoutAValidFormTokenIsRefused(): void
    {
        $delegate = $this->createDelegate();

        $this->requestLink($delegate->email, signed: false);

        self::assertSame([], $this->sent);
        self::assertContains(LoginController::LOGIN_ERROR_TOKEN, $this->flash('error'));
    }

    public function testASecondRequestWithinTheCooldownIsRefusedWithoutBreakingTheFirstLink(): void
    {
        // config/config.php sets magicLink.cooldownSeconds. Each request invalidates the
        // previous link, so without the cooldown anyone could keep a delegate's link
        // permanently broken by holding down refresh on a public form.
        $delegate = $this->createDelegate();
        $this->requestLink($delegate->email);
        $firstUrl = $this->sent[0]['loginUrl'];

        $this->requestLink($delegate->email);

        self::assertCount(1, $this->sent, 'the second request must not send anything');
        self::assertContains(LoginController::MAGIC_LINK_THROTTLED, $this->flash('error'));
        self::assertTrue($this->tokens->findByToken($this->tokenFrom($firstUrl))->isUsable());
    }

    // ---- what following a link establishes -------------------------------------------

    public function testAValidTokenLogsTheDelegateInAsADelegate(): void
    {
        // loggedInEntityType is what AuthMiddleware uses to pick the repository, and what
        // every delegate-scoped controller branches on. Getting 'user' here would hand a
        // delegate the staff view of the dashboard.
        $delegate = $this->createDelegate();
        $this->requestLink($delegate->email);

        $response = $this->followLink($this->tokenFrom($this->sent[0]['loginUrl']));

        self::assertSame($delegate->getId(), $this->storage->offsetGet('loggedIn'));
        self::assertSame('delegate', $this->storage->offsetGet('loggedInEntityType'));
        self::assertSame($delegate->getAuthRole(), $this->storage->offsetGet('loggedInRole'));
        self::assertSame($delegate->email, $this->storage->offsetGet('loggedInEmail'));
        self::assertStringEndsWith('/beneficiary/view/', $response->getHeaderLine('Location'));
    }

    public function testMerelyFetchingTheLinkDoesNotSpendItOrLogAnybodyIn(): void
    {
        // Mail clients and in-app browsers prefetch links to build previews. While a GET
        // consumed the token, that prefetch burned it seconds after it was issued — on
        // phones only, which is why it looked like an intermittent fault for so long.
        $delegate = $this->createDelegate();
        $this->requestLink($delegate->email);
        $token = $this->tokenFrom($this->sent[0]['loginUrl']);

        $this->peekLink($token);

        self::assertTrue($this->tokens->findByToken($token)->isUsable());
        self::assertNull($this->storage->offsetGet('loggedIn'));
    }

    public function testALinkWorksOnlyOnce(): void
    {
        $delegate = $this->createDelegate();
        $this->requestLink($delegate->email);
        $token = $this->tokenFrom($this->sent[0]['loginUrl']);
        $this->followLink($token);

        $this->storage->clear();
        $response = $this->followLink($token);

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertStringEndsWith(self::FORM_PATH, $response->getHeaderLine('Location'));
    }

    public function testARejectedTokenEstablishesNoSessionAtAll(): void
    {
        // The important half of a login test. A failed verify must leave nothing behind — a
        // half-written session is worse than none, because AuthMiddleware only checks that
        // 'loggedIn' is truthy.
        $response = $this->followLink(str_repeat('a', 128));

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertNull($this->storage->offsetGet('loggedInEntityType'));
        self::assertContains(LoginController::MAGIC_LINK_INVALID, $this->flash('error'));
        self::assertSame(302, $response->getStatusCode());
    }

    public function testADelegateDeactivatedAfterTheLinkWasSentCannotUseIt(): void
    {
        // The window between issuing and following is exactly where a suspension lands.
        $delegate = $this->createDelegate();
        $this->requestLink($delegate->email);
        $token = $this->tokenFrom($this->sent[0]['loginUrl']);

        $delegate->status = Delegate::STATUS_PROBLEM;
        $this->em()->flush();

        $this->followLink($token);

        self::assertNull($this->storage->offsetGet('loggedIn'));
    }

    public function testARequestWithNoTokenNeverReachesTheAuthenticator(): void
    {
        $response = $this->peekLink(null);

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertStringEndsWith(self::FORM_PATH, $response->getHeaderLine('Location'));
    }

    // ---- already signed in, and leaving ---------------------------------------------

    public function testADelegateWhoIsAlreadySignedInIsSentStraightToTheirWork(): void
    {
        $this->storage->offsetSet('loggedIn', 7);
        $this->storage->offsetSet('redirectPath', '/beneficiary/view/');

        $controller = $this->controller();
        $controller->setRequest(
            (new ServerRequest('GET', self::FORM_PATH))->withAttribute('entityType', 'delegate')
        );

        $response = $controller->magicLinkForm();

        self::assertSame(302, $response->getStatusCode());
        self::assertStringEndsWith('/beneficiary/view/', $response->getHeaderLine('Location'));
    }

    public function testLoggingOutReturnsADelegateToTheDelegateDoor(): void
    {
        // The door is derived from config auth.default, which is magic_link here -- the
        // framework's own default is a password form this app does not have.
        $this->storage->offsetSet('loggedIn', 7);
        $this->storage->offsetSet('loggedInEntityType', 'delegate');

        $controller = $this->controller();
        $controller->setRequest(new ServerRequest('GET', '/login/logout'));

        $response = $controller->logOut();

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertStringEndsWith(self::FORM_PATH, $response->getHeaderLine('Location'));
    }

    public function testLoggingOutWithNoEntityTypeFallsBackToTheStaffDoor(): void
    {
        // Inherited from Unit\Backend\LogoutTest, which covered Backend\Action\Logout until
        // that action was replaced by the framework's logOut(). The case worth keeping is the
        // fallback: a session with no entity type recorded still has to land on a magic-link
        // form, because auth.methods does not include the password form it would otherwise
        // default to. The delegate case above and this one bracket the whole method.
        $this->storage->offsetSet('loggedIn', 7);

        $controller = $this->controller();
        $controller->setRequest(new ServerRequest('GET', '/login/logout'));

        $response = $controller->logOut();

        self::assertNull($this->storage->offsetGet('loggedIn'));
        self::assertStringEndsWith('/login/user/magicLinkForm/', $response->getHeaderLine('Location'));
    }

    // ---- driving the endpoints ---------------------------------------------------------

    private function requestLink(?string $email, bool $signed = true): ResponseInterface
    {
        $body = $email === null ? [] : ['email' => $email];
        if ($signed) {
            $body += (new Csrf($this->session))->getTokenAsArray();
        }

        $controller = $this->controller();
        $controller->setRequest(
            (new ServerRequest('POST', '/login/delegate/requestMagicLink/'))
                ->withAttribute('entityType', 'delegate')
                ->withParsedBody($body),
        );

        return $controller->requestMagicLink();
    }

    /** GET the link: confirms it is alive without spending it. */
    private function peekLink(?string $token): ResponseInterface
    {
        $request = (new ServerRequest('GET', '/login/delegate/verifyMagicLink/'))
            ->withAttribute('entityType', 'delegate');
        if ($token !== null) {
            $request = $request->withAttribute('token', $token);
        }

        $controller = $this->controller();
        $controller->setRequest($request);

        return $controller->verifyMagicLink();
    }

    /** POST the link: spends it. */
    private function followLink(string $token): ResponseInterface
    {
        $controller = $this->controller();
        $controller->setRequest(
            (new ServerRequest('POST', '/login/delegate/verifyMagicLink/'))
                ->withAttribute('entityType', 'delegate')
                ->withParsedBody(['token' => $token]),
        );

        return $controller->verifyMagicLink();
    }

    /** The token out of the URL the mailer was handed — the only place the plaintext exists. */
    private function tokenFrom(string $magicLinkUrl): string
    {
        return basename(rtrim((string) parse_url($magicLinkUrl, PHP_URL_PATH), '/'));
    }

    /**
     * Plates pointed at this app's admin theme.
     *
     * The GET leg of verifyMagicLink() renders a real page, and Controller::respond() turns a
     * missing template into a var_dump rather than an exception — which under
     * beStrictAboutOutputDuringTests fails as output, not as a helpful message. The 't'
     * function is required outright: the framework controller passes every flash message
     * through translate().
     */
    private function templateEngine(): Engine
    {
        $themes = dirname(__DIR__, 3) . '/themes/admin';
        $engine = new Engine($themes);
        $engine->addFolder('layout', $themes . '/layout');
        $engine->addFolder('defaultTheme', $themes);
        $engine->registerFunction('formToken', fn (): string => (new Csrf($this->session))->getHiddenInputString());
        $engine->registerFunction('t', fn (string $text): string => $text);
        $engine->registerFunction('getVersionPathPrefix', fn (): string => '');

        return $engine;
    }

    // ---- collaborators ------------------------------------------------------------------

    private function controller(): LoginController
    {
        $csrf = new Csrf($this->session);
        // The app's real config, so the cooldown, the link lifetime and the per-entity URL
        // templates under test are the ones that will be deployed.
        $config = new Config(
            require dirname(__DIR__, 3) . '/config/config.php'
        );
        $config = $config->merge(new Config(['adminUrl' => 'https://admin.example.com', 'adminPath' => '']));

        $mailer = $this->createStub(\Skeletor\Core\Mailer\Service\MailerInterface::class);
        $mailer->method('sendMagicLinkEmail')->willReturnCallback(
            function (string $email, ?string $displayName, string $loginUrl): void {
                $this->sent[] = compact('email', 'displayName', 'loginUrl');
            },
        );

        $magicLinks = new MagicLinkService(
            new TokenGenerator(),
            $this->tokens,
            $this->registry,
            $mailer,
            $config,
        );

        $forgotPasswords = new ForgotPasswordRepository($this->em());

        // The same node config/config.php carries: magic link only, no second factor. Built
        // from the merged test config rather than stubbed, so a change to the app's auth
        // settings shows up here as a failing test rather than as a silently divergent stack.
        $policy = new AuthPolicy($config);

        return new LoginController(
            new Login(null, $this->session, $mailer, $forgotPasswords, null, $this->registry),
            $this->session,
            $config,
            new Flash(),
            $this->templateEngine(),
            new NullLogger(),
            new ForgotPasswordFilter(new ForgotPasswordValidator($forgotPasswords, $csrf)),
            new LoginFilter(new LoginValidator($csrf)),
            new ResetPassword(new ResetPasswordLoose($forgotPasswords, $csrf)),
            $forgotPasswords,
            $magicLinks,
            new AuthenticatorRegistry(
                $policy,
                new PasswordAuthenticator($this->registry, $policy),
                new MagicLinkAuthenticator($this->registry, $policy, $this->tokens),
            ),
            $this->registry,
            $policy,
            new PendingAuthentication($this->session),
            null,
        );
    }

    /** @return string[] */
    private function flash(string $type): array
    {
        return $_SESSION['flash_messages'][$type] ?? [];
    }
}
