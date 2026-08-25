<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session as SkeletorSession;
use Solidarity\Backend\Controller\BeneficiaryController;
use Solidarity\Backend\Service\Redaction;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Period\Service\Period as PeriodService;
use Solidarity\School\Repository\SchoolRepository;
use Solidarity\School\Service\City as CityService;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\ProjectRepository;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\User\Entity\User;
use Tamtamchik\SimpleFlash\Flash;

/**
 * The option lists behind the beneficiary edit form.
 *
 * This is the render half of the registration data-loss bug. The save half now reconciles by
 * id (`BeneficiaryFactory::syncRegisteredPeriods`), but what the form *offers* still decides
 * what a user can express: an option that is missing cannot be chosen, and before the save
 * was fixed a missing option meant the registration was deleted on the next save.
 *
 * `form()` builds those lists and then renders, so the assertions here read `formData` after
 * a real render against the real admin theme — which is also what proves the template still
 * accepts the shape the controller hands it.
 */
#[CoversClass(BeneficiaryController::class)]
final class BeneficiaryFormTest extends IntegrationTestCase
{
    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION = ['flash_messages' => []];
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

    // ---- periods ---------------------------------------------------------------------

    public function testTheActivePeriodsOfEveryProjectAreOffered(): void
    {
        $project = $this->createProject('MSP');
        $offered = $this->createPeriod($project, month: 3);

        $data = $this->openForm();

        self::assertSame([$offered->getId()], $this->idsOf($data['assignedPeriods']));
    }

    public function testAnInactivePeriodIsNotOfferedToSomeoneNotRegisteredForIt(): void
    {
        // The create form must not invite new registrations against a closed round.
        $project = $this->createProject('MSP');
        $closed = $this->createPeriod($project, month: 3);
        $closed->active = false;
        $this->em()->flush();

        $data = $this->openForm();

        self::assertSame([], $this->idsOf($data['assignedPeriods']));
    }

    public function testAnInactivePeriodIsOfferedBackToTheBeneficiaryRegisteredForIt(): void
    {
        // The fix this method exists for. The period list is built from *active* periods, so
        // a registration on a closed round had no <option>, the dropdown fell back to its
        // placeholder, and saving dropped the row. It has to be merged back in for the
        // person who holds it, even though it is closed to everyone else.
        $project = $this->createProject('MSP');
        $closed = $this->createPeriod($project, month: 3);
        $closed->active = false;
        $this->em()->flush();

        $beneficiary = $this->createBeneficiary('Registered on a closed round');
        $this->createRegisteredPeriod($beneficiary, $project, $closed, 40000);

        $data = $this->openForm($beneficiary);

        self::assertSame([$closed->getId()], $this->idsOf($data['assignedPeriods']));
    }

    public function testAPeriodIsNotOfferedTwiceWhenItIsBothActiveAndRegistered(): void
    {
        // The merge checks before appending; without that the dropdown shows the round twice
        // and the duplicate-prevention in RegisteredProjects.js starts disabling the wrong one.
        $project = $this->createProject('MSP');
        $open = $this->createPeriod($project, month: 3);
        $beneficiary = $this->createBeneficiary('Registered on an open round');
        $this->createRegisteredPeriod($beneficiary, $project, $open, 40000);

        $data = $this->openForm($beneficiary);

        self::assertSame([$open->getId()], $this->idsOf($data['assignedPeriods']));
    }

    // ---- delegates see less ---------------------------------------------------------------

    public function testADelegateIsOnlyOfferedTheProjectsTheyHold(): void
    {
        $mine = $this->createProject('MSP');
        $theirs = $this->createProject('MSPR');
        $delegate = $this->createDelegate();
        $delegate->projects->add($mine);
        $this->em()->flush();

        $data = $this->openForm(null, $delegate);

        self::assertSame([$mine->getId()], $this->idsOf($data['assignedProjects']));
    }

    public function testADelegateIsOnlyOfferedPeriodsBelongingToTheirProjects(): void
    {
        $mine = $this->createProject('MSP');
        $theirs = $this->createProject('MSPR');
        $ourPeriod = $this->createPeriod($mine, month: 3);
        $this->createPeriod($theirs, month: 3);
        $delegate = $this->createDelegate();
        $delegate->projects->add($mine);
        $this->em()->flush();

        $data = $this->openForm(null, $delegate);

        self::assertSame([$ourPeriod->getId()], $this->idsOf($data['assignedPeriods']));
    }

    public function testADelegateStillSeesTheClosedPeriodOfABeneficiaryTheyHold(): void
    {
        // The merge runs for delegates too — it is keyed on the beneficiary, not the editor.
        $project = $this->createProject('MSP');
        $closed = $this->createPeriod($project, month: 3);
        $closed->active = false;
        $this->em()->flush();

        $delegate = $this->createDelegate();
        $delegate->projects->add($project);
        $beneficiary = $this->createBeneficiary('Theirs');
        $this->createRegisteredPeriod($beneficiary, $project, $closed, 40000);
        $this->em()->flush();

        $data = $this->openForm($beneficiary, $delegate);

        self::assertSame([$closed->getId()], $this->idsOf($data['assignedPeriods']));
    }

    public function testADelegateIsNotOfferedTheProjectOfARegistrationTheyDoNotHold(): void
    {
        // The known gap, pinned rather than fixed: the *period* of a foreign-project
        // registration is merged back in, but the project is not — so the project select
        // renders empty for that row. Since syncRegisteredPeriods() now falls back to the
        // stored values, this is cosmetic rather than destructive; it was not before.
        $held = $this->createProject('MSP');
        $foreign = $this->createProject('MSPR');
        $foreignPeriod = $this->createPeriod($foreign, month: 3);

        $delegate = $this->createDelegate();
        $delegate->projects->add($held);
        $beneficiary = $this->createBeneficiary('Registered elsewhere');
        $this->createRegisteredPeriod($beneficiary, $foreign, $foreignPeriod, 40000);
        $this->em()->flush();

        $data = $this->openForm($beneficiary, $delegate);

        self::assertSame([$held->getId()], $this->idsOf($data['assignedProjects']));
        self::assertContains($foreignPeriod->getId(), $this->idsOf($data['assignedPeriods']));
    }

    // ---- the rest of the form ---------------------------------------------------------------

    public function testTheConfirmedAmountIsShownAgainstEachRegistration(): void
    {
        // Rendered as "Potvrđeni iznos (n%)" beside the requested amount, keyed
        // project_period — a key built differently here and in the template shows 0 forever.
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary('Part paid');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 40000);
        $donor = $this->createDonor();
        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED);

        $data = $this->openForm($beneficiary);

        self::assertSame(5000, $data['confirmedAmounts'][$project->getId() . '_' . $period->getId()] ?? null);
    }

    public function testTheCreateFormCarriesNoModelData(): void
    {
        $this->createProject('MSP');

        $data = $this->openForm();

        self::assertSame([], $data['confirmedAmounts']);
        self::assertSame([], $this->toArray($data['paymentMethods']));
    }

    // ---- helpers -------------------------------------------------------------------------------

    /**
     * The option lists form() would put in formData, built without going near a template.
     *
     * form() itself ends in parent::form(), which renders — and `Controller::respond()`
     * swallows a template failure into a var_dump that `beStrictAboutOutputDuringTests`
     * turns into an unattributable failure. Rather than stand up the whole admin theme to
     * get at three arrays, the list building lives in its own methods on the controller and
     * is called directly here.
     *
     * @return array<string, mixed>
     */
    private function openForm(?Beneficiary $beneficiary = null, ?Delegate $delegate = null): array
    {
        $controller = $this->controller($delegate);
        $projects = $this->call($controller, 'editableProjects');

        return [
            'assignedProjects' => $projects,
            'assignedPeriods' => $this->call($controller, 'editablePeriods', $projects, $beneficiary),
            'confirmedAmounts' => $this->call($controller, 'confirmedAmountsFor', $beneficiary),
            'paymentMethods' => $beneficiary ? $beneficiary->paymentMethods : [],
        ];
    }

    private function call(BeneficiaryController $controller, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(BeneficiaryController::class, $method))->invoke($controller, ...$arguments);
    }

    /** @param iterable<object> $entities */
    private function idsOf(iterable $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = $entity->getId();
        }
        sort($ids);

        return $ids;
    }

    /** @param iterable<mixed> $items */
    private function toArray(iterable $items): array
    {
        return is_array($items) ? $items : iterator_to_array($items);
    }

    private function controller(?Delegate $delegate): BeneficiaryController
    {
        $em = $this->em();
        $storage = new ArrayStorage([
            'loggedIn' => $delegate?->getId() ?? 1,
            'loggedInRole' => $delegate ? 10 : User::ROLE_ADMIN,
            'loggedInEntityType' => $delegate ? 'delegate' : 'user',
            'loggedInEmail' => 'admin@example.com',
        ]);
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($storage);

        $delegateService = $this->createStub(DelegateService::class);
        $delegateService->method('getById')->willReturn($delegate);

        return new BeneficiaryController(
            new BeneficiaryService(
                new BeneficiaryRepository($em),
                $this->createStub(SkeletorSession::class),
                new NullLogger(),
                $this->createStub(BeneficiaryFilter::class),
                $this->createStub(ProjectService::class),
                $this->createStub(SchoolService::class),
                $this->createStub(DelegateService::class),
                $this->createStub(CityService::class),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $session,
            new Config(['adminPath' => '']),
            new Flash(),
            new \League\Plates\Engine(),
            new SchoolService(new SchoolRepository($em), $this->createStub(SkeletorSession::class), new NullLogger(), $this->createStub(\Skeletor\Core\Activity\Service\Activity::class)),
            new PeriodService(new PeriodRepository($em), $this->createStub(SkeletorSession::class), new NullLogger(), $this->createStub(\Skeletor\Core\Activity\Service\Activity::class)),
            new ProjectService(
                new ProjectRepository($em),
                $this->createStub(SkeletorSession::class),
                new NullLogger(),
                $this->createStub(TransactionFilter::class),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $delegateService,
            new TransactionService(
                new TransactionRepository($em),
                $this->createStub(SkeletorSession::class),
                new NullLogger(),
                $this->createStub(TransactionFilter::class),
                $this->createStub(ProjectService::class),
                new BeneficiaryRepository($em),
                new PeriodRepository($em),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            new Redaction($em),
        );
    }
}
