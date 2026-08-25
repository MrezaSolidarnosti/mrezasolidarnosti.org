<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Skeletor\Login\Service\MagicLinkService;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Skeletor\Translator\Service\Translator;
use Skeletor\User\Service\Session as UserSession;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as ProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Frontend\Service\Session;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\ProjectRepository;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;
use Skeletor\Core\Security\Csrf;

/**
 * Shared scaffolding for the frontend actions.
 *
 * BaseAction's constructor does a surprising amount of work before any action code runs:
 * it reads baseUrl from config, resolves social links, asks the session for a display
 * name, stamps the donor's visit, parses $_SERVER['REQUEST_URI'] and reads the flash bag
 * out of $_SESSION. Every one of those has to be satisfied or the action cannot be
 * constructed at all, which is why the actions went untested for so long — this class
 * pays that cost once.
 */
abstract class FrontendActionTestCase extends IntegrationTestCase
{
    protected const BASE_URL = 'http://solidarity.local';

    protected const VALID_TOKEN = 'valid-csrf-token';

    /** Tamtamchik\SimpleFlash\Core\SessionManager's private $key — it exposes no constant. */
    private const FLASH_KEY = 'flash_messages';

    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? null;
        $this->serverBackup = $_SERVER;

        // SimpleFlash indexes $_SESSION directly without starting a session, so it has to
        // be an array or BaseAction's constructor dies on array_key_exists(null).
        //
        // flash_messages has to be seeded too. SessionManager creates that key in its
        // *constructor*, and Flash is a static singleton — so it runs once per process,
        // during whichever test happens to build an action first. Replacing $_SESSION
        // afterwards drops the key, and every later read warns because getSession()
        // indexes it without a guard.
        $_SESSION = [
            Csrf::TOKEN_NAME => self::VALID_TOKEN,
            self::FLASH_KEY => [],
        ];
        // BaseAction derives the "slug" template global from this.
        $_SERVER['REQUEST_URI'] = '/test';
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    // ---- BaseAction's six constructor arguments -----------------------------

    protected function logger(): NullLogger
    {
        return new NullLogger();
    }

    protected function config(): Config
    {
        // BaseAction reads baseUrl unconditionally; anything else it needs is optional.
        return new Config(['baseUrl' => self::BASE_URL]);
    }

    /**
     * The Location header as a root-relative path. Html::redirect() prefixes baseUrl to
     * anything without a scheme, so asserting on '/registrovani-ste' directly fails
     * against 'http://solidarity.local/registrovani-ste'.
     */
    protected function redirectPath(ResponseInterface $response): string
    {
        $location = $response->getHeaderLine('Location');

        return str_starts_with($location, self::BASE_URL)
            ? substr($location, strlen(self::BASE_URL))
            : $location;
    }

    protected function engine(): Engine
    {
        return new Engine();
    }

    protected function navigation(): Navigation
    {
        return $this->createStub(Navigation::class);
    }

    protected function socialLinks(): SocialLinks
    {
        $socialLinks = $this->createStub(SocialLinks::class);
        // getSocialLinks() foreaches over this, so it must be iterable, not null.
        $socialLinks->method('getSocialItems')->willReturn([]);

        return $socialLinks;
    }

    /** A frontend session for $donor, or an anonymous one when null. */
    protected function session(?Donor $donor = null): Session
    {
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn($donor !== null);
        $session->method('getDisplayName')->willReturn($donor?->email);
        $session->method('getId')->willReturn($donor?->getId());
        $session->method('getUser')->willReturn($donor);

        return $session;
    }

    /**
     * A DonorService wired to the real database, for the actions that reach one.
     *
     * Only the collaborators the donor-facing paths actually use are real: the repositories,
     * the donation-data filter/validator pair, the transaction service and the QR builder
     * (whether a row gets a QR is part of the instructions payload). The rest are stubs.
     */
    protected function realDonorService(): DonorService
    {
        $em = $this->em();

        // Real too: a stubbed filter returns nothing, so the data the allocator assembles
        // never reaches TransactionFactory and every field arrives undefined. The failure
        // surfaces as "Undefined array key amount" from inside the factory rather than as
        // anything resembling a wiring problem.
        $transactionFilter = new TransactionFilter(new TransactionValidator(
            new CsrfTrueStub(),
            new DonorRepository($em),
            new BeneficiaryRepository($em),
        ));

        return new DonorService(
            new DonorRepository($em),
            $this->createStub(UserSession::class),
            new NullLogger(),
            $this->createStub(DonorFilter::class),
            $this->createStub(Mailer::class),
            // Real: createTransaction() resolves the submitted project id through this, and
            // a stub hands back null — which the allocator reads as "no projects to give
            // to" and reports as a NoNeedsException rather than a wiring problem.
            new ProjectService(
                new ProjectRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                $transactionFilter,
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $this->createStub(MagicLinkService::class),
            $this->createStub(ProfileDataFilter::class),
            $this->createStub(ProfileDataValidator::class),
            new DonationDataValidator(new CsrfTrueStub()),
            new DonationDataFilter(),
            new TransactionService(
                new TransactionRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                $transactionFilter,
                $this->createStub(ProjectService::class),
                new BeneficiaryRepository($em),
                new PeriodRepository($em),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            new QrCode(),
            // With no language set translate() is a pass-through, so the Serbian source
            // comes back unchanged — a stub returning null would blank every status label.
            $this->translator(),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }

    protected function translator(): Translator
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('translate')->willReturnArgument(0);

        return $translator;
    }

    // ---- request / response helpers -------------------------------------------

    /**
     * A POST carrying a valid CSRF token, matching what the session was seeded with.
     *
     * @param array<string, mixed> $body
     */
    protected function post(array $body = []): ServerRequest
    {
        return (new ServerRequest('POST', '/test'))
            ->withParsedBody($body + [Csrf::TOKEN_NAME => self::VALID_TOKEN]);
    }

    /**
     * A POST carrying a specific token.
     *
     * Csrf::validate() **regenerates the token whenever it succeeds**, so a token is good
     * for exactly one request and the session's copy has moved on by the time the response
     * is written. Any test making two calls in a row has to carry the token the previous
     * response handed back, which is what the browser does too.
     *
     * @param array<string, mixed> $body
     */
    protected function postWithToken(string $token, array $body = []): ServerRequest
    {
        return (new ServerRequest('POST', '/test'))
            ->withParsedBody($body + [Csrf::TOKEN_NAME => $token]);
    }

    /**
     * A POST whose token does not match the session's — Csrf::validate() reads both out
     * of $_SESSION and the body, so forging one is just a matter of sending the wrong one.
     *
     * @param array<string, mixed> $body
     */
    protected function forgedPost(array $body = []): ServerRequest
    {
        return (new ServerRequest('POST', '/test'))
            ->withParsedBody($body + [Csrf::TOKEN_NAME => 'not-the-session-token']);
    }

    protected function emptyResponse(): Response
    {
        return new Response();
    }

    /**
     * The decoded JSON envelope every frontend action returns:
     * ['success' => bool, 'data' => [...]].
     *
     * @return array<string, mixed>
     */
    protected function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /** @return string[] flattened, since actions nest validator messages one level deep */
    protected function errorsFrom(ResponseInterface $response): array
    {
        $errors = $this->decode($response)['data']['errors'] ?? [];
        $flat = [];
        array_walk_recursive($errors, static function ($message) use (&$flat): void {
            $flat[] = $message;
        });

        return $flat;
    }
}
