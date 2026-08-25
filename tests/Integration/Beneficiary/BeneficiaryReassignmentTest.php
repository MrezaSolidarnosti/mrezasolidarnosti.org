<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\School\Entity\School;
use Solidarity\Tests\Integration\IntegrationTestCase;

/**
 * Beneficiary <-> delegate reassignment, driven by Delegate::create()/update() whenever a
 * delegate's school list changes. Both methods are raw SQL, so they bypass the ORM
 * entirely and there is nothing but these tests standing between them and the data.
 *
 * "Orphaned" means createdBy_id IS NULL: a beneficiary imported or created without a
 * delegate. Claiming one is how a newly verified delegate inherits the people already
 * registered at their school.
 */
#[CoversClass(BeneficiaryRepository::class)]
final class BeneficiaryReassignmentTest extends IntegrationTestCase
{
    // ---- assignOrphanedBeneficiariesToDelegate -----------------------------

    public function testAnOrphanAtTheSchoolIsClaimed(): void
    {
        $school = $this->school();
        $delegate = $this->createDelegate();
        $orphan = $this->createBeneficiary('Orphan', school: $school);

        $this->repo()->assignOrphanedBeneficiariesToDelegate($school->getId(), $delegate->getId());

        self::assertSame($delegate->getId(), $this->reload($orphan)->createdBy?->getId());
    }

    public function testABeneficiaryAlreadyOwnedByAnotherDelegateIsLeftAlone(): void
    {
        // Only NULL is claimable — otherwise adding a school to a delegate would quietly
        // take over people another delegate already vouches for.
        $school = $this->school();
        $owner = $this->createDelegate();
        $claimant = $this->createDelegate();
        $owned = $this->createBeneficiary('Owned', school: $school, createdBy: $owner);

        $this->repo()->assignOrphanedBeneficiariesToDelegate($school->getId(), $claimant->getId());

        self::assertSame($owner->getId(), $this->reload($owned)->createdBy?->getId());
    }

    public function testOrphansAtOtherSchoolsAreNotClaimed(): void
    {
        $school = $this->school();
        $otherSchool = $this->school('Other School');
        $delegate = $this->createDelegate();
        $elsewhere = $this->createBeneficiary('Elsewhere', school: $otherSchool);

        $this->repo()->assignOrphanedBeneficiariesToDelegate($school->getId(), $delegate->getId());

        self::assertNull($this->reload($elsewhere)->createdBy);
    }

    public function testEveryOrphanAtTheSchoolIsClaimedAtOnce(): void
    {
        $school = $this->school();
        $delegate = $this->createDelegate();
        $first = $this->createBeneficiary('First', school: $school);
        $second = $this->createBeneficiary('Second', school: $school);

        $this->repo()->assignOrphanedBeneficiariesToDelegate($school->getId(), $delegate->getId());

        self::assertSame($delegate->getId(), $this->reload($first)->createdBy?->getId());
        self::assertSame($delegate->getId(), $this->reload($second)->createdBy?->getId());
    }

    // ---- nullifyCreatedByForDelegate ---------------------------------------

    public function testReleasingADelegateOrphansTheirBeneficiaries(): void
    {
        $school = $this->school();
        $delegate = $this->createDelegate();
        $theirs = $this->createBeneficiary('Theirs', school: $school, createdBy: $delegate);

        $this->repo()->nullifyCreatedByForDelegate($delegate->getId());

        self::assertNull($this->reload($theirs)->createdBy);
    }

    public function testReleasingADelegateLeavesOtherDelegatesBeneficiariesAlone(): void
    {
        $school = $this->school();
        $delegate = $this->createDelegate();
        $other = $this->createDelegate();
        $theirs = $this->createBeneficiary('Theirs', school: $school, createdBy: $delegate);
        $notTheirs = $this->createBeneficiary('Not theirs', school: $school, createdBy: $other);

        $this->repo()->nullifyCreatedByForDelegate($delegate->getId());

        self::assertNull($this->reload($theirs)->createdBy);
        self::assertSame($other->getId(), $this->reload($notTheirs)->createdBy?->getId());
    }

    public function testReleasingADelegateIgnoresTheSchoolAndClearsAllOfTheirs(): void
    {
        // Current behaviour, and a sharp edge worth knowing about. Delegate::update()
        // calls this whenever ANY school is removed, but the query is scoped to the
        // delegate, not to the removed school. A delegate covering two schools who loses
        // one is detached from the beneficiaries of BOTH, and only the schools that were
        // *added* in the same edit get reclaimed — so the untouched school's people are
        // silently orphaned.
        $kept = $this->school('Kept School');
        $removed = $this->school('Removed School');
        $delegate = $this->createDelegate();

        $atKept = $this->createBeneficiary('At kept', school: $kept, createdBy: $delegate);
        $atRemoved = $this->createBeneficiary('At removed', school: $removed, createdBy: $delegate);

        $this->repo()->nullifyCreatedByForDelegate($delegate->getId());

        self::assertNull($this->reload($atRemoved)->createdBy);
        self::assertNull($this->reload($atKept)->createdBy);
    }

    // ---- the create/update round trip --------------------------------------

    public function testReleasingThenReassigningRestoresOnlyTheRemainingSchool(): void
    {
        // The two calls in the order Delegate::update() makes them: nullify everything,
        // then reclaim the schools still on the list. Anything at a school that was
        // neither removed nor re-added stays orphaned until someone notices.
        $kept = $this->school('Kept School');
        $removed = $this->school('Removed School');
        $delegate = $this->createDelegate();

        $atKept = $this->createBeneficiary('At kept', school: $kept, createdBy: $delegate);
        $atRemoved = $this->createBeneficiary('At removed', school: $removed, createdBy: $delegate);

        $this->repo()->nullifyCreatedByForDelegate($delegate->getId());
        $this->repo()->assignOrphanedBeneficiariesToDelegate($kept->getId(), $delegate->getId());

        self::assertSame($delegate->getId(), $this->reload($atKept)->createdBy?->getId());
        self::assertNull($this->reload($atRemoved)->createdBy);
    }

    // ---- helpers -------------------------------------------------------------

    private function repo(): BeneficiaryRepository
    {
        return new BeneficiaryRepository($this->em());
    }

    private function school(string $name = 'Test School'): School
    {
        return $this->createSchool($this->createCity(), null, $name);
    }

    private function reload(Beneficiary $beneficiary): Beneficiary
    {
        // Both methods are raw SQL, so the identity map knows nothing about the change.
        $id = $beneficiary->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Beneficiary::class)->find($id);
    }
}
