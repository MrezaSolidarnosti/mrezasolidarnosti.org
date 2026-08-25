<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Delegate;

use PHPUnit\Framework\Attributes\CoversNothing;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\School\Entity\School;
use Solidarity\Tests\Integration\IntegrationTestCase;

/**
 * What survives a delegate being deleted.
 *
 * Delegates leave rows behind them in three places, and each is meant to react differently.
 * Before this was pinned, `school.delegate_id` had no ON DELETE rule at all, so MySQL
 * defaulted to RESTRICT and a delegate holding any school could not be deleted — while
 * `AjaxCrudController::delete()` swallowed the constraint violation into a generic "could
 * not delete" with its logger call commented out, so nothing said why. Beneficiaries were
 * the natural suspect and never the cause.
 *
 * The schema here is built by Doctrine SchemaTool from the mappings, so these exercise the
 * real ON DELETE rules rather than a hand-written test schema.
 */
#[CoversNothing]
final class DelegateDeletionTest extends IntegrationTestCase
{
    public function testADelegateHoldingSchoolsCanBeDeleted(): void
    {
        // The case that was impossible. A school outliving its delegate is a normal state.
        $delegate = $this->createDelegate();
        $school = $this->createSchool($this->createCity('Nis'), name: 'Osnovna skola');
        $school->delegate = $delegate;
        $this->em()->flush();
        $schoolId = $school->getId();

        $this->em()->remove($delegate);
        $this->em()->flush();
        $this->em()->clear();

        $survivor = $this->em()->find(School::class, $schoolId);
        self::assertNotNull($survivor, 'the school must outlive its delegate');
        self::assertNull($survivor->delegate, 'and be left unassigned rather than dangling');
    }

    public function testTheBeneficiariesADelegateRegisteredOutliveThem(): void
    {
        // createdBy is SET NULL, so the registrations stay and only lose their attribution.
        // Deleting a delegate must never take donations-in-progress with it.
        $delegate = $this->createDelegate();
        $beneficiary = $this->createBeneficiary('Registered by the delegate', createdBy: $delegate);
        $beneficiaryId = $beneficiary->getId();

        $this->em()->remove($delegate);
        $this->em()->flush();
        $this->em()->clear();

        $survivor = $this->em()->find(Beneficiary::class, $beneficiaryId);
        self::assertNotNull($survivor);
        self::assertNull($survivor->createdBy);
    }

    public function testTheProjectsADelegateHeldAreUnaffected(): void
    {
        // Only the delegate_project rows go; the projects themselves are shared and must
        // not follow one delegate out.
        $project = $this->createProject('MSP');
        $delegate = $this->createDelegate();
        $delegate->projects->add($project);
        $this->em()->flush();
        $projectId = $project->getId();

        $this->em()->remove($delegate);
        $this->em()->flush();
        $this->em()->clear();

        self::assertNotNull($this->em()->find(\Solidarity\Transaction\Entity\Project::class, $projectId));
    }

    public function testDeletingOneDelegateLeavesAnothersSchoolAlone(): void
    {
        // SET NULL is scoped by the foreign key, but a cleanup written as a broad UPDATE
        // would not be — worth pinning which one is in force.
        $city = $this->createCity('Kragujevac');
        $going = $this->createDelegate();
        $staying = $this->createDelegate();

        $theirs = $this->createSchool($city, name: 'Njihova skola');
        $theirs->delegate = $going;
        $ours = $this->createSchool($city, name: 'Nasa skola');
        $ours->delegate = $staying;
        $this->em()->flush();
        $oursId = $ours->getId();
        $stayingId = $staying->getId();

        $this->em()->remove($going);
        $this->em()->flush();
        $this->em()->clear();

        $untouched = $this->em()->find(School::class, $oursId);
        self::assertSame($stayingId, $untouched->delegate?->getId());
    }
}
