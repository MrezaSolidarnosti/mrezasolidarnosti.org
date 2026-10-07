<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\School\Entity\School;
use Solidarity\School\Repository\SchoolRepository;
use Solidarity\School\Service\City;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project;

/**
 * What a delegate gets to see, now that a school can have several delegates.
 *
 * All delegates of a school are at the same level: each sees every beneficiary of the
 * school, and the transactions of those beneficiaries. createdBy only decides access for a
 * school-less (MSPR) beneficiary; for a school one it is just the contact on record.
 *
 * The rule is BeneficiaryRepository::addDelegateScope() (DQL) and
 * Beneficiary::isVisibleToDelegate() (PHP, for one loaded entity). Both are exercised here
 * on the same fixture so they cannot drift apart unnoticed.
 */
#[CoversClass(BeneficiaryRepository::class)]
#[CoversClass(TransactionRepository::class)]
#[CoversClass(SchoolRepository::class)]
final class DelegateScopeTest extends IntegrationTestCase
{
    private Delegate $first;
    private Delegate $second;
    private Delegate $outsider;
    private School $shared;
    private Beneficiary $atShared;
    private Beneficiary $msprOfFirst;
    private Beneficiary $atOutsiders;

    protected function setUp(): void
    {
        parent::setUp();

        $city = $this->createCity('Novi Sad');
        $this->first = $this->createDelegate();
        $this->second = $this->createDelegate();
        $this->outsider = $this->createDelegate();

        $this->shared = $this->createSchool($city, name: 'Velika skola');
        $this->assignSchool($this->first, $this->shared);
        $this->assignSchool($this->second, $this->shared);
        $outsidersSchool = $this->createSchool($city, name: 'Druga skola');
        $this->assignSchool($this->outsider, $outsidersSchool);

        // Contact is the first delegate, but the second must see them just the same.
        $this->atShared = $this->createBeneficiary('At shared school', school: $this->shared, createdBy: $this->first);
        $this->msprOfFirst = $this->createBeneficiary('MSPR of first', school: null, createdBy: $this->first);
        $this->atOutsiders = $this->createBeneficiary('At outsiders school', school: $outsidersSchool, createdBy: $this->outsider);
    }

    // ---- beneficiary list ---------------------------------------------------

    public function testEveryDelegateOfASchoolSeesItsBeneficiaries(): void
    {
        self::assertSame(
            $this->ids([$this->atShared, $this->msprOfFirst]),
            $this->visibleBeneficiaries($this->first),
        );
        self::assertSame($this->ids([$this->atShared]), $this->visibleBeneficiaries($this->second));
    }

    public function testADelegateOfAnotherSchoolSeesNoneOfThem(): void
    {
        self::assertSame($this->ids([$this->atOutsiders]), $this->visibleBeneficiaries($this->outsider));
    }

    public function testASchoolBeneficiaryIsNotVisibleThroughCreatedByAlone(): void
    {
        // The contact left the school: createdBy still names them, access does not follow.
        $this->shared->delegates->removeElement($this->first);
        $this->first->schools->removeElement($this->shared);
        $this->em()->flush();

        self::assertSame($this->ids([$this->msprOfFirst]), $this->visibleBeneficiaries($this->first));
    }

    public function testTheTotalAgreesWithThePage(): void
    {
        $repo = new BeneficiaryRepository($this->em());

        self::assertSame(2, $repo->getTotalCount([BeneficiaryRepository::DELEGATE_SCOPE => $this->first->getId()]));
        self::assertSame(1, $repo->getTotalCount([BeneficiaryRepository::DELEGATE_SCOPE => $this->second->getId()]));
    }

    public function testTheStaffDelegateColumnFilterUsesTheSameRule(): void
    {
        // Filtering the staff list by "Delegat" = second must list the shared school's
        // beneficiary even though createdBy names the first.
        $result = (new BeneficiaryRepository($this->em()))
            ->fetchTableData(null, ['createdBy' => (string) $this->second->getId()], 0, 50, []);

        self::assertSame($this->ids([$this->atShared]), $this->ids($result['items']));
    }

    public function testThePhpRuleMatchesTheQuery(): void
    {
        $service = $this->beneficiaryService();

        self::assertTrue($service->isVisibleToDelegate($this->atShared, $this->first->getId()));
        self::assertTrue($service->isVisibleToDelegate($this->atShared, $this->second->getId()));
        self::assertFalse($service->isVisibleToDelegate($this->atShared, $this->outsider->getId()));
        self::assertTrue($service->isVisibleToDelegate($this->msprOfFirst, $this->first->getId()));
        self::assertFalse($service->isVisibleToDelegate($this->msprOfFirst, $this->second->getId()));
        self::assertFalse($service->isVisibleToDelegate(null, $this->first->getId()));
    }

    // ---- transactions -------------------------------------------------------

    public function testACoDelegateSeesAndMayActOnTheSchoolsTransactions(): void
    {
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $atShared = $this->createTransaction($donor, $this->atShared, $project, $period, 5000);
        $atOutsiders = $this->createTransaction($donor, $this->atOutsiders, $project, $period, 5000);

        $repo = new TransactionRepository($this->em());
        $scope = [BeneficiaryRepository::DELEGATE_SCOPE => $this->second->getId()];

        self::assertSame(
            $this->ids([$atShared]),
            $this->ids($repo->fetchTableData(null, [], 0, 50, [], $scope)['items']),
        );
        self::assertSame(1, $repo->getTotalCount($scope));
        self::assertTrue($repo->belongsToDelegate($atShared->getId(), $this->second->getId()));
        self::assertFalse($repo->belongsToDelegate($atOutsiders->getId(), $this->second->getId()));
    }

    // ---- schools ------------------------------------------------------------

    public function testTheSchoolListFiltersOnAnyOfItsDelegates(): void
    {
        $repo = new SchoolRepository($this->em());
        $this->createSchool($this->createCity('Novi Sad'), name: 'Skola bez delegata');

        $forSecond = $repo->fetchTableData(null, [], 0, 50, [], ['d.id' => $this->second->getId()]);
        self::assertSame($this->ids([$this->shared]), $this->ids($forSecond['items']));
        self::assertSame(1, $repo->getTotalCount(['d.id' => $this->second->getId()]));

        // The beneficiary form's school search: schools with at least one delegate, each once
        // even when it has two (the join would otherwise duplicate the shared school).
        $withDelegates = $repo->fetchTableData(null, ['d.id' => 'not_null'], 0, 50, []);
        self::assertCount(2, $withDelegates['items']);
    }

    // ---- helpers ------------------------------------------------------------

    /** @return int[] sorted ids of the beneficiaries $delegate sees in the list */
    private function visibleBeneficiaries(Delegate $delegate): array
    {
        $result = (new BeneficiaryRepository($this->em()))->fetchTableData(
            null, [], 0, 50, [], [BeneficiaryRepository::DELEGATE_SCOPE => $delegate->getId()]
        );

        return $this->ids($result['items']);
    }

    /** @param iterable<object> $entities */
    private function ids(iterable $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = $entity->getId();
        }
        sort($ids);

        return $ids;
    }

    private function beneficiaryService(): BeneficiaryService
    {
        return new BeneficiaryService(
            new BeneficiaryRepository($this->em()),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(BeneficiaryFilter::class),
            $this->createStub(Project::class),
            $this->createStub(SchoolService::class),
            $this->createStub(DelegateService::class),
            $this->createStub(City::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
