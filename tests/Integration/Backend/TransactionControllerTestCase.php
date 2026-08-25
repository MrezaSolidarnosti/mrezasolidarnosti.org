<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session as SkeletorSession;
use Solidarity\Backend\Controller\TransactionController;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Period\Entity\Period;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Period\Service\Period as PeriodService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\User\Entity\User;
use Tamtamchik\SimpleFlash\Flash;

/**
 * Scaffolding for the TransactionController endpoints.
 *
 * The controller takes eleven collaborators; only the TransactionService is real, because
 * only its repository work (the sums, the status writes) is what these tests are about.
 * The four lookup services are stubbed to hand back entities the test built itself — the
 * endpoints call nothing on them but getById(), and going through the real services would
 * drag in the mailer, the QR-code builder and the magic-link service for nothing.
 */
abstract class TransactionControllerTestCase extends IntegrationTestCase
{
    /** Tamtamchik\SimpleFlash\Core\SessionManager's private $key — it exposes no constant. */
    protected const FLASH_KEY = 'flash_messages';

    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    private ?TransactionService $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? null;
        // SimpleFlash indexes $_SESSION directly without starting a session, and its engine
        // is a process-wide singleton — so the bag has to be reseeded per test or messages
        // from an earlier test leak into this one's assertions.
        $_SESSION = [self::FLASH_KEY => []];
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }
        $this->service = null;

        parent::tearDown();
    }

    protected function service(): TransactionService
    {
        return $this->service ??= new TransactionService(
            new TransactionRepository($this->em()),
            $this->createStub(SkeletorSession::class),
            new NullLogger(),
            $this->createStub(TransactionFilter::class),
            $this->createStub(ProjectService::class),
            new BeneficiaryRepository($this->em()),
            new PeriodRepository($this->em()),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }

    /**
     * The lookup arguments stand in for whatever getById() would have returned for the ids
     * in the request; pass null to model "no such row".
     */
    protected function controller(
        int $role = User::ROLE_ADMIN,
        ?Donor $donor = null,
        ?Beneficiary $beneficiary = null,
        ?Project $project = null,
        ?Period $period = null,
    ): TransactionController {
        // AjaxCrudController's constructor redirects when 'loggedIn' is empty, and
        // TransactionController reads the role to decide on the create button.
        $storage = new ArrayStorage(['loggedIn' => 1, 'loggedInRole' => $role]);
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($storage);

        return new TransactionController(
            $this->service(),
            $session,
            new Config(['adminPath' => '']),
            new Flash(),
            $this->engine(),
            $this->lookup(DonorService::class, $donor),
            $this->lookup(ProjectService::class, $project),
            $this->createStub(Mailer::class),
            $this->lookup(PeriodService::class, $period),
            $this->createStub(DelegateService::class),
            $this->lookup(BeneficiaryService::class, $beneficiary),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function lookup(string $class, ?object $entity): object
    {
        $stub = $this->createStub($class);
        $stub->method('getById')->willReturn($entity);

        return $stub;
    }

    /**
     * Controller::translate() resolves through Plates — $template->make('t')->t($string) —
     * so a bare Engine throws "template function could not be found" the moment an endpoint
     * translates anything. updateStatusBulk() does exactly that in its outer catch, which
     * would turn a reported failure into an uncaught LogicException.
     */
    private function engine(): Engine
    {
        $engine = new Engine();
        $engine->registerFunction('t', static fn (string $string): string => $string);

        return $engine;
    }

    protected function get(string $path, array $queryParams = []): ServerRequest
    {
        return (new ServerRequest('GET', $path))->withQueryParams($queryParams);
    }

    /** A POST whose body is the JSON envelope the bulk endpoints read with json_decode(). */
    protected function postJson(string $path, array $body, array $queryParams = []): ServerRequest
    {
        return (new ServerRequest('POST', $path, [], json_encode($body)))->withQueryParams($queryParams);
    }

    /** @return array<string, mixed> */
    protected function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /** @return string[] */
    protected function flashErrors(): array
    {
        return $_SESSION[self::FLASH_KEY]['error'] ?? [];
    }
}
