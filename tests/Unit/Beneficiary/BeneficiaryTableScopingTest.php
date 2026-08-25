<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\School\Service\City;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Service\Project;

/**
 * The delegate scoping on the beneficiary list.
 *
 * This is a permission boundary, not a convenience filter: delegates share one dashboard
 * with staff, and the only thing stopping a delegate from reading every beneficiary in
 * the country is that fetchTableData() injects createdBy into the query. The assertions
 * below are on the arguments handed to the repository, because that is where the boundary
 * actually is — anything that reaches the repository unscoped is already a leak.
 */
#[CoversClass(BeneficiaryService::class)]
final class BeneficiaryTableScopingTest extends TestCase
{
    public function testADelegateOnlySeesBeneficiariesTheyCreated(): void
    {
        $repo = $this->repositoryExpecting(function (?array $uncountableFilter): void {
            self::assertSame(42, $uncountableFilter['createdBy'] ?? null);
        });

        $this->service($repo, 'delegate', 42)->fetchTableData(null, [], 0, 10, []);
    }

    public function testStaffSeeEveryBeneficiary(): void
    {
        $repo = $this->repositoryExpecting(function (?array $uncountableFilter): void {
            self::assertArrayNotHasKey('createdBy', $uncountableFilter ?? []);
        });

        $this->service($repo, 'user', 1)->fetchTableData(null, [], 0, 10, []);
    }

    public function testTheDelegateScopeIsAddedToAnyFilterTheCallerPassed(): void
    {
        // The scope must survive alongside the caller's own filters rather than replacing
        // them — overwriting the array would widen the result set back out.
        $repo = $this->repositoryExpecting(function (?array $uncountableFilter): void {
            self::assertSame(42, $uncountableFilter['createdBy'] ?? null);
            self::assertSame(7, $uncountableFilter['school'] ?? null);
        });

        $this->service($repo, 'delegate', 42)->fetchTableData(null, [], 0, 10, [], ['school' => 7]);
    }

    public function testADelegateCannotWidenTheScopeByPassingTheirOwnCreatedByFilter(): void
    {
        // The injected value wins, so a crafted request asking for another delegate's
        // beneficiaries still comes back scoped to the caller.
        $repo = $this->repositoryExpecting(function (?array $uncountableFilter): void {
            self::assertSame(42, $uncountableFilter['createdBy'] ?? null);
        });

        $this->service($repo, 'delegate', 42)->fetchTableData(null, [], 0, 10, [], ['createdBy' => 999]);
    }

    // ---- helpers ------------------------------------------------------------

    /** A repository that runs $assert against the uncountableFilter it is handed. */
    private function repositoryExpecting(callable $assert): BeneficiaryRepository
    {
        $repo = $this->createMock(BeneficiaryRepository::class);
        $repo->expects(self::once())
            ->method('fetchTableData')
            ->willReturnCallback(function (
                $search,
                $filter,
                $offset,
                $limit,
                $order,
                $uncountableFilter = null,
                $idsToInclude = [],
                $idsToExclude = []
            ) use ($assert): array {
                $assert($uncountableFilter);

                return ['count' => 0, 'items' => [], 'countColumnData' => []];
            });

        return $repo;
    }

    private function service(BeneficiaryRepository $repo, string $entityType, int $userId): BeneficiaryService
    {
        $session = $this->createStub(Session::class);
        $session->method('getLoggedInEntityType')->willReturn($entityType);
        $session->method('getLoggedInUserId')->willReturn($userId);

        return new BeneficiaryService(
            $repo,
            $session,
            new NullLogger(),
            $this->createStub(BeneficiaryFilter::class),
            $this->createStub(Project::class),
            $this->createStub(School::class),
            $this->createStub(Delegate::class),
            $this->createStub(City::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
