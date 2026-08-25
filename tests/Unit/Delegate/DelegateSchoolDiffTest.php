<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Delegate;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Delegate\Entity\Delegate as DelegateEntity;
use Solidarity\Delegate\Filter\Delegate as DelegateFilter;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\School\Entity\School;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\School\Service\SchoolType;
use Solidarity\Transaction\Service\Project;

/**
 * Who owns a school's beneficiaries after the delegate list is edited.
 *
 * A beneficiary's createdBy is what lets a delegate see and edit them, so moving a school
 * between delegates has to hand the beneficiaries over with it. The service does that by
 * diffing the submitted school list against the stored one, and the two halves are not
 * symmetric: additions are handled per-school, removals are not.
 *
 * These are unit tests — the repositories are doubles. What is under test is the diff and
 * the order of the calls, not the SQL, which BeneficiaryReassignmentTest covers against a
 * real database.
 */
#[CoversClass(DelegateService::class)]
final class DelegateSchoolDiffTest extends TestCase
{
    // ---- create ---------------------------------------------------------------

    public function testANewDelegateClaimsTheOrphansOfEverySchoolTheyAreGiven(): void
    {
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::exactly(2))
            ->method('assignOrphanedBeneficiariesToDelegate')
            ->willReturnCallback(function (int $schoolId, int $delegateId): void {
                self::assertContains($schoolId, [4, 5]);
                self::assertSame(77, $delegateId);
            });

        $this->service($this->repositoryReturning($this->delegate(77)), $beneficiaries)
            ->create(['email' => 'd@example.com', 'schools' => [4, 5]]);
    }

    public function testADelegateWithNoSchoolsTouchesNoBeneficiaries(): void
    {
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::never())->method('assignOrphanedBeneficiariesToDelegate');

        $this->service($this->repositoryReturning($this->delegate(77)), $beneficiaries)
            ->create(['email' => 'd@example.com']);
    }

    public function testJunkInTheSchoolListIsDiscardedRatherThanAssignedToSchoolZero(): void
    {
        // The select posts strings, and an empty option posts ''. Both intval to 0, which
        // as a school id would match nothing — but it would still run the UPDATE.
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::once())
            ->method('assignOrphanedBeneficiariesToDelegate')
            ->with(4, 77);

        $this->service($this->repositoryReturning($this->delegate(77)), $beneficiaries)
            ->create(['email' => 'd@example.com', 'schools' => ['4', '', 'abc', 0]]);
    }

    // ---- update: the diff -------------------------------------------------------

    public function testAddingASchoolClaimsItsOrphansAndLeavesTheRestAlone(): void
    {
        // The other half of the reclaim rule: with nothing released there is nothing to
        // restore, so school 4 is left untouched. Reclaiming the whole list unconditionally
        // would mean any unrelated edit swept up orphans an admin had deliberately
        // unassigned — hence the reclaim widens only when a removal actually happened.
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::once())
            ->method('assignOrphanedBeneficiariesToDelegate')
            ->with(5, 77);
        $beneficiaries->expects(self::never())->method('nullifyCreatedByForDelegate');

        $this->update($beneficiaries, oldSchoolIds: [4], newSchoolIds: [4, 5]);
    }

    public function testSubmittingTheSameSchoolsChangesNoOwnership(): void
    {
        // Every unrelated edit — a phone number, a status — resubmits the school list.
        // Treating that as a change would rewrite ownership on every save.
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::never())->method('assignOrphanedBeneficiariesToDelegate');
        $beneficiaries->expects(self::never())->method('nullifyCreatedByForDelegate');

        $this->update($beneficiaries, oldSchoolIds: [4, 5], newSchoolIds: [5, 4]);
    }

    public function testRemovingASchoolReleasesTheDelegatesBeneficiaries(): void
    {
        $beneficiaries = $this->createMock(BeneficiaryRepository::class);
        $beneficiaries->expects(self::once())->method('nullifyCreatedByForDelegate')->with(77);

        $this->update($beneficiaries, oldSchoolIds: [4, 5], newSchoolIds: [4]);
    }

    public function testRemovingOneSchoolLeavesTheKeptSchoolsStillOwned(): void
    {
        // The regression this method was built around. nullifyCreatedByForDelegate is
        // scoped to the *delegate*, not to the school that triggered it, so removing
        // school 5 releases school 4's beneficiaries as collateral. School 4 is neither
        // added nor removed, so reclaiming only the added schools would leave it
        // permanently ownerless — the reclaim has to cover everything still on the list.
        $this->update($this->recorder(), oldSchoolIds: [4, 5], newSchoolIds: [4]);

        self::assertSame([['nullify', 77], ['update', 77], ['assign', 4, 77]], $this->calls);
    }

    public function testASimultaneousAddAndRemoveReclaimsBothTheKeptAndTheAddedSchool(): void
    {
        $this->update($this->recorder(), oldSchoolIds: [4, 5], newSchoolIds: [4, 6]);

        self::assertSame(
            [['nullify', 77], ['update', 77], ['assign', 4, 77], ['assign', 6, 77]],
            $this->calls,
        );
    }

    public function testTheReleaseHappensBeforeTheReclaim(): void
    {
        // Order is the whole correctness argument: nullify runs first and clears everything,
        // then the added schools claim what they need. Reversed, the nullify would undo the
        // assignment it was supposed to precede.
        $this->update($this->recorder(), oldSchoolIds: [4], newSchoolIds: [5]);

        self::assertSame([['nullify', 77], ['update', 77], ['assign', 5, 77]], $this->calls);
    }

    public function testClearingTheSchoolListReleasesEverythingAndClaimsNothing(): void
    {
        $this->update($this->recorder(), oldSchoolIds: [4, 5], newSchoolIds: []);

        self::assertSame([['nullify', 77], ['update', 77]], $this->calls);
    }

    // ---- helpers -------------------------------------------------------------------

    /**
     * @param int[] $oldSchoolIds
     * @param int[] $newSchoolIds
     */
    private function update(BeneficiaryRepository $beneficiaries, array $oldSchoolIds, array $newSchoolIds): void
    {
        // A stub, not a mock: nothing here asserts on the repository's calls (the order is
        // asserted through the shared log in recorder()), and a mock with no expects()
        // raises a PHPUnit notice — which failOnWarning turns into noise across seven tests.
        $repo = $this->createStub(DelegateRepository::class);
        $repo->method('getById')->willReturn($this->delegateWithSchools(77, $oldSchoolIds));
        $repo->method('update')->willReturnCallback(function (): DelegateEntity {
            $this->record('update', 77);

            return $this->delegate(77);
        });

        $this->service($repo, $beneficiaries)->update(['id' => 77, 'schools' => $newSchoolIds]);
    }

    /** @var list<array<int, mixed>> */
    private array $calls = [];

    private function recorder(): BeneficiaryRepository
    {
        // One log shared with the repository double, so the assertions can cover the order
        // of the reassignment calls *relative to the update itself* — which is the part
        // that matters, and which expects()->after() cannot express across two doubles.
        $beneficiaries = $this->createStub(BeneficiaryRepository::class);
        $beneficiaries->method('nullifyCreatedByForDelegate')
            ->willReturnCallback(fn (int $id) => $this->record('nullify', $id));
        $beneficiaries->method('assignOrphanedBeneficiariesToDelegate')
            ->willReturnCallback(fn (int $schoolId, int $delegateId) => $this->record('assign', $schoolId, $delegateId));

        return $beneficiaries;
    }

    private function record(string $name, int ...$args): void
    {
        $this->calls[] = [$name, ...$args];
    }

    private function delegate(int $id): DelegateEntity
    {
        $entity = $this->createStub(DelegateEntity::class);
        $entity->method('getId')->willReturn($id);

        return $entity;
    }

    /** @param int[] $schoolIds */
    private function delegateWithSchools(int $id, array $schoolIds): DelegateEntity
    {
        $schools = array_map(function (int $schoolId): School {
            $school = $this->createStub(School::class);
            $school->method('getId')->willReturn($schoolId);

            return $school;
        }, $schoolIds);

        $delegate = $this->delegate($id);
        $delegate->schools = new ArrayCollection($schools);

        return $delegate;
    }

    private function repositoryReturning(DelegateEntity $entity): DelegateRepository
    {
        $repo = $this->createStub(DelegateRepository::class);
        $repo->method('create')->willReturn($entity);
        $repo->method('update')->willReturn($entity);

        return $repo;
    }

    private function service(DelegateRepository $repo, BeneficiaryRepository $beneficiaries): DelegateService
    {
        $filter = $this->createStub(DelegateFilter::class);
        // The filter has its own tests; here it must not swallow the keys under test.
        $filter->method('filter')->willReturnArgument(0);

        return new DelegateService(
            $repo,
            $this->createStub(Session::class),
            new NullLogger(),
            $filter,
            new \DateTime('2026-08-11 12:00:00'),
            $this->createStub(SchoolType::class),
            $this->createStub(Project::class),
            $beneficiaries,
            $this->createStub(SchoolService::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
