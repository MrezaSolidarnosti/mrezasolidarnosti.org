<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session as SkeletorSession;
use Solidarity\Backend\Controller\SchoolController;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\School\Entity\School;
use Solidarity\School\Repository\SchoolRepository;
use Solidarity\School\Service\City as CityService;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\School\Service\SchoolType as SchoolTypeService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\User\Entity\User;
use Tamtamchik\SimpleFlash\Flash;

/**
 * The school detail screen: who is allowed to open it, and the figures on it.
 *
 * Two separate things live in SchoolController. The first is an access check — a delegate
 * may only open a school assigned to them, enforced by a redirect in form(). The second is
 * getSchoolStatsByPeriod(), a private block of aggregate DQL that reports, per period, how
 * many beneficiaries this school has, what they asked for, and what has actually moved
 * split across four transaction statuses. It is the only place those numbers are computed.
 *
 * Only the *denied* branch of form() is exercised here: the allowed branch falls through to
 * parent::form(), which renders a Plates template — and Controller::respond() swallows a
 * failure into a var_dump, which under beStrictAboutOutputDuringTests fails the run with no
 * useful message. The stats are reached directly by reflection instead, which is where the
 * logic worth testing actually is.
 */
#[CoversClass(SchoolController::class)]
final class SchoolControllerTest extends IntegrationTestCase
{
    private const DELEGATE_ROLE = 10;

    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Constructing a Flash builds a SimpleFlash SessionManager, which indexes $_SESSION
        // without a guard. It only happens for the first Flash in the process — so without
        // this the suite passes or warns depending on which test ran first.
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

    // ---- who may open a school -------------------------------------------------

    public function testADelegateOpeningASchoolHeldByAnotherDelegateIsSentBackToTheList(): void
    {
        $city = $this->createCity();
        $theirs = $this->createSchool($city, null, 'Their School');
        $someoneElses = $this->createSchool($city, null, 'Someone Elses School');

        $response = $this->openForm($someoneElses, holding: [$theirs]);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringEndsWith('/school/view/', $response->getHeaderLine('Location'));
    }

    public function testADelegateOpeningASchoolNobodyHoldsIsSentBackToTheList(): void
    {
        // Unassigned schools are the common case — most of the table belongs to nobody, and
        // the delegate can see the rows. Being able to see a row must not mean being able to
        // open it.
        $unassigned = $this->createSchool($this->createCity(), null, 'Unassigned School');

        $response = $this->openForm($unassigned, holding: []);

        self::assertSame(302, $response->getStatusCode());
    }

    // ---- the figures on the screen ------------------------------------------------

    public function testASchoolWithNobodyRegisteredHasNothingToReport(): void
    {
        $school = $this->createSchool($this->createCity());

        self::assertSame([], $this->stats($school));
    }

    public function testItReportsHowManyPeopleRegisteredAndWhatTheyAskedFor(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());

        $this->createRegisteredPeriod($this->createBeneficiary('A', $school), $project, $period, 40000);
        $this->createRegisteredPeriod($this->createBeneficiary('B', $school), $project, $period, 25000);

        $stats = $this->stats($school);

        self::assertCount(1, $stats);
        self::assertSame($period->getId(), $stats[0]['period']->getId());
        self::assertSame(2, $stats[0]['beneficiaryCount']);
        self::assertSame(65000, $stats[0]['requestedAmount']);
    }

    public function testSomeoneRegisteredForTwoProjectsInOnePeriodCountsOnceButAsksTwice(): void
    {
        // A beneficiary gets one RegisteredPeriods row per project, so the same period can
        // hold two rows for one person. The head count is DISTINCT and the money is not —
        // which is right, but only if you know the rows are per project.
        $msp = $this->createProject('MSP');
        $mspr = $this->createProject('MSPR');
        $period = $this->createPeriod($msp);
        $school = $this->createSchool($this->createCity());
        $beneficiary = $this->createBeneficiary('Registered twice', $school);

        $this->createRegisteredPeriod($beneficiary, $msp, $period, 30000);
        $this->createRegisteredPeriod($beneficiary, $mspr, $period, 20000);

        $stats = $this->stats($school);

        self::assertSame(1, $stats[0]['beneficiaryCount']);
        self::assertSame(50000, $stats[0]['requestedAmount']);
    }

    public function testOnlyPeriodsThisSchoolActuallyHasRegistrationsForAreListed(): void
    {
        $project = $this->createProject();
        $ours = $this->createPeriod($project, month: 3);
        $theirs = $this->createPeriod($project, month: 4);
        $city = $this->createCity();
        $school = $this->createSchool($city, null, 'Ours');
        $otherSchool = $this->createSchool($city, null, 'Theirs');

        $this->createRegisteredPeriod($this->createBeneficiary('A', $school), $project, $ours, 1000);
        $this->createRegisteredPeriod($this->createBeneficiary('B', $otherSchool), $project, $theirs, 1000);

        $stats = $this->stats($school);

        self::assertCount(1, $stats);
        self::assertSame($ours->getId(), $stats[0]['period']->getId());
    }

    public function testPeriodsAreListedNewestFirst(): void
    {
        // Sorted by year then month descending, so the current round is at the top of the
        // panel rather than buried under a year of history.
        $project = $this->createProject();
        $school = $this->createSchool($this->createCity());
        $oldest = $this->createPeriod($project, month: 11, year: 2025);
        $middle = $this->createPeriod($project, month: 1, year: 2026);
        $newest = $this->createPeriod($project, month: 2, year: 2026);

        foreach ([$middle, $newest, $oldest] as $index => $period) {
            $this->createRegisteredPeriod(
                $this->createBeneficiary('B' . $index, $school), $project, $period, 1000,
            );
        }

        $ids = array_map(static fn (array $row): int => $row['period']->getId(), $this->stats($school));

        self::assertSame([$newest->getId(), $middle->getId(), $oldest->getId()], $ids);
    }

    public function testDeletedBeneficiariesAreLeftOutOfTheHeadCountAndTheRequestedTotal(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());

        $this->createRegisteredPeriod($this->createBeneficiary('Still here', $school), $project, $period, 40000);
        $erased = $this->createBeneficiary('Erased', $school, Beneficiary::STATUS_DELETED);
        $this->createRegisteredPeriod($erased, $project, $period, 25000);

        $stats = $this->stats($school);

        self::assertSame(1, $stats[0]['beneficiaryCount']);
        self::assertSame(40000, $stats[0]['requestedAmount']);
    }

    public function testMoneyIsSplitAcrossTheFourTransactionStatuses(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());
        $beneficiary = $this->createBeneficiary('A', $school);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 100000);
        $donor = $this->createDonor();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $project, $period, 3000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $project, $period, 7000, Transaction::STATUS_PAID);
        $this->createTransaction($donor, $beneficiary, $project, $period, 1000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 9000, Transaction::STATUS_CANCELLED);

        $stats = $this->stats($school)[0];

        self::assertSame([8000, 2], [$stats['confirmedAmount'], $stats['confirmedCount']]);
        self::assertSame([7000, 1], [$stats['paidAmount'], $stats['paidCount']]);
        // "active" is STATUS_NEW — an instruction that has gone out and not been answered yet.
        self::assertSame([1000, 1], [$stats['activeAmount'], $stats['activeCount']]);
        self::assertSame([9000, 1], [$stats['cancelledAmount'], $stats['cancelledCount']]);
    }

    public function testAPeriodWithRegistrationsButNoMoneyYetReportsZerosRatherThanGaps(): void
    {
        // The template reads every one of these keys; a missing one is an undefined-index
        // warning on the page rather than a blank cell.
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());
        $this->createRegisteredPeriod($this->createBeneficiary('A', $school), $project, $period, 40000);

        $stats = $this->stats($school)[0];

        foreach (['confirmed', 'paid', 'active', 'cancelled'] as $bucket) {
            self::assertSame(0, $stats[$bucket . 'Amount'], $bucket);
            self::assertSame(0, $stats[$bucket . 'Count'], $bucket);
        }
    }

    public function testTransactionsOfADeletedBeneficiaryStillCountTowardsTheMoney(): void
    {
        // The head count and the requested total exclude STATUS_DELETED; the four money
        // buckets do not filter on beneficiary status at all. So an erased person is absent
        // from "2 people asked for 40,000" while the 5,000 already sent on their behalf is
        // still in "confirmed". Defensible — the money did move — but the two halves of one
        // row are counting different populations, and nothing says so on screen.
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());
        $donor = $this->createDonor();

        $this->createRegisteredPeriod($this->createBeneficiary('Still here', $school), $project, $period, 40000);
        $erased = $this->createBeneficiary('Erased', $school, Beneficiary::STATUS_DELETED);
        $this->createRegisteredPeriod($erased, $project, $period, 25000);
        $this->createTransaction($donor, $erased, $project, $period, 5000, Transaction::STATUS_CONFIRMED);

        $stats = $this->stats($school)[0];

        self::assertSame(1, $stats['beneficiaryCount']);
        self::assertSame(40000, $stats['requestedAmount']);
        self::assertSame(5000, $stats['confirmedAmount']);
    }

    // ---- helpers -------------------------------------------------------------------

    /**
     * getSchoolStatsByPeriod() is private and has no caller other than form(), which cannot
     * be run to completion without a template. Reflection is how Statistics::getStats() is
     * reached too.
     *
     * @return list<array<string, mixed>>
     */
    private function stats(School $school): array
    {
        return (new \ReflectionMethod(SchoolController::class, 'getSchoolStatsByPeriod'))
            ->invoke($this->controller(), $school->getId());
    }

    /** @param School[] $holding the schools assigned to the delegate making the request */
    private function openForm(School $school, array $holding): ResponseInterface
    {
        $delegate = $this->createDelegate();
        $delegate->schools = new ArrayCollection($holding);

        $controller = $this->controller(self::DELEGATE_ROLE, 'delegate', $delegate);
        $controller->setRequest(
            (new ServerRequest('GET', '/school/form/'))->withAttribute('id', (string) $school->getId()),
        );

        return $controller->form();
    }

    private function controller(
        int $role = User::ROLE_ADMIN,
        string $entityType = 'user',
        ?Delegate $delegate = null,
    ): SchoolController {
        $storage = new ArrayStorage([
            'loggedIn' => $delegate?->getId() ?? 1,
            'loggedInRole' => $role,
            'loggedInEntityType' => $entityType,
        ]);
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($storage);

        $delegateService = $this->createStub(DelegateService::class);
        $delegateService->method('getById')->willReturn($delegate);

        return new SchoolController(
            new SchoolService(
                new SchoolRepository($this->em()),
                $this->createStub(SkeletorSession::class),
                new NullLogger(),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $session,
            new Config(['adminPath' => '']),
            new Flash(),
            new Engine(),
            $this->createStub(CityService::class),
            $this->createStub(SchoolTypeService::class),
            // The stats are the point, so the EntityManager is the one real collaborator.
            $this->em(),
            $delegateService,
        );
    }
}
