<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Beneficiary\Factory\BeneficiaryFactory;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(BeneficiaryFactory::class)]
final class BeneficiaryFactoryTest extends IntegrationTestCase
{
    public function testCreatePersistsBeneficiaryWithPeriodsPaymentMethodsAndRelations(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $school = $this->createSchool($this->createCity());
        $delegate = $this->createDelegate();

        $id = BeneficiaryFactory::compileEntityForCreate([
            'id' => null,
            'name' => 'Factory Beneficiary',
            'status' => Beneficiary::STATUS_NEW,
            'comment' => null,
            'school' => $school->getId(),
            'createdBy' => $delegate->getId(),
            'registeredPeriods' => [
                ['project' => $project->getId(), 'period' => $period->getId(), 'amount' => 5000],
            ],
            'paymentMethods' => [
                ['type' => 1, 'accountNumber' => '000000000000000098', 'wireInstructions' => null],
            ],
        ], $this->em());

        $this->em()->clear();
        $beneficiary = $this->em()->find(Beneficiary::class, $id);

        self::assertSame('Factory Beneficiary', $beneficiary->name);
        self::assertSame($school->getId(), $beneficiary->school->getId());
        self::assertSame($delegate->getId(), $beneficiary->createdBy->getId());

        self::assertCount(1, $beneficiary->registeredPeriods);
        self::assertSame(5000, $beneficiary->registeredPeriods->first()->amount);

        self::assertCount(1, $beneficiary->paymentMethods);
        self::assertSame(1, $beneficiary->paymentMethods->first()->type);
        self::assertSame('000000000000000098', $beneficiary->paymentMethods->first()->accountNumber);
    }

    public function testUpdateReplacesRegisteredPeriodsAndPaymentMethods(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $newPeriod = $this->createPeriod($project, month: 9);

        // Seed a beneficiary with one period + one payment method.
        $beneficiary = $this->createBeneficiary();
        $this->createRegisteredPeriod($beneficiary, $project, $period, 1000);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: 1, accountNumber: '000000000000000098');
        $beneficiaryId = $beneficiary->getId();

        BeneficiaryFactory::compileEntityForUpdate([
            'id' => $beneficiaryId,
            'name' => 'Updated Beneficiary',
            'status' => Beneficiary::STATUS_NEW,
            'comment' => null,
            'school' => null,
            'createdBy' => null,
            'registeredPeriods' => [
                ['project' => $project->getId(), 'period' => $newPeriod->getId(), 'amount' => 7000],
            ],
            'paymentMethods' => [
                ['type' => 2, 'accountNumber' => null, 'wireInstructions' => 'SWIFT'],
            ],
        ], $this->em());

        $this->em()->clear();
        $beneficiary = $this->em()->find(Beneficiary::class, $beneficiaryId);

        self::assertSame('Updated Beneficiary', $beneficiary->name);

        self::assertCount(1, $beneficiary->registeredPeriods);
        self::assertSame(7000, $beneficiary->registeredPeriods->first()->amount);

        self::assertCount(1, $beneficiary->paymentMethods);
        self::assertSame(2, $beneficiary->paymentMethods->first()->type);
        self::assertSame('SWIFT', $beneficiary->paymentMethods->first()->wireInstructions);
    }

    // ---- reconciling registered periods by id --------------------------------------
    //
    // The form cannot always express what is stored — a delegate editing a beneficiary
    // registered under a project outside their own assigned list gets a project <select>
    // with no matching <option>, which posts back the placeholder's -1. Rebuilding the list
    // from the form therefore used to delete that registration, and the amount with it, on
    // an edit that had nothing to do with it. These pin the reconcile that replaced it.

    public function testAStoredRegistrationIsUpdatedInPlaceRatherThanReplaced(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 1000);
        $registrationId = $registration->getId();

        $this->update($beneficiary->getId(), [
            ['id' => $registrationId, 'project' => $project->getId(), 'period' => $period->getId(), 'amount' => 4000],
        ]);

        $this->em()->clear();
        $survivor = $this->em()->find(RegisteredPeriods::class, $registrationId);

        // Same row, not a delete-and-reinsert: ids and timestamps stay put across a save.
        self::assertNotNull($survivor);
        self::assertSame(4000, $survivor->amount);
    }

    public function testARegistrationWhoseProjectTheFormCouldNotRenderSurvivesTheSave(): void
    {
        // The delegate case exactly: the period select still posts its real value, the
        // project select posts -1. That -1 resolved to nothing and took the whole row with it.
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 40000);
        $registrationId = $registration->getId();

        $this->update($beneficiary->getId(), [
            ['id' => $registrationId, 'project' => -1, 'period' => $period->getId(), 'amount' => 40000],
        ]);

        $this->em()->clear();
        $survivor = $this->em()->find(RegisteredPeriods::class, $registrationId);

        self::assertNotNull($survivor, 'the registration must not be destroyed by an unrelated edit');
        self::assertSame($project->getId(), $survivor->project->getId());
        self::assertSame($period->getId(), $survivor->period->getId());
        self::assertSame(40000, $survivor->amount);
    }

    public function testARegistrationNeitherSelectCouldRenderSurvivesUntouched(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 40000);
        $registrationId = $registration->getId();

        $this->update($beneficiary->getId(), [
            ['id' => $registrationId, 'project' => -1, 'period' => 0, 'amount' => 40000],
        ]);

        $this->em()->clear();
        $survivor = $this->em()->find(RegisteredPeriods::class, $registrationId);

        self::assertNotNull($survivor);
        self::assertSame($project->getId(), $survivor->project->getId());
        self::assertSame($period->getId(), $survivor->period->getId());
    }

    public function testAnAmountEditIsAppliedEvenWhenTheSelectsCameBackUnusable(): void
    {
        // The deliberate half of "preserve": the amount input renders from the model and is
        // always editable, so a change to it is a real instruction. Ignoring it would trade
        // one silent failure for another. Only the identity falls back to what is stored.
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 40000);
        $registrationId = $registration->getId();

        $this->update($beneficiary->getId(), [
            ['id' => $registrationId, 'project' => -1, 'period' => 0, 'amount' => 25000],
        ]);

        $this->em()->clear();
        self::assertSame(25000, $this->em()->find(RegisteredPeriods::class, $registrationId)->amount);
    }

    public function testARegistrationTheUserActuallyDeletedIsRemoved(): void
    {
        // The other half: preserving must not mean nothing can ever be removed. Pressing
        // Delete takes the row out of the DOM, so it is simply not submitted.
        $project = $this->createProject('MSPR');
        $kept = $this->createPeriod($project);
        $removed = $this->createPeriod($project, month: 9);
        $beneficiary = $this->createBeneficiary();
        $keptRegistration = $this->createRegisteredPeriod($beneficiary, $project, $kept, 1000);
        $removedRegistration = $this->createRegisteredPeriod($beneficiary, $project, $removed, 2000);
        $keptId = $keptRegistration->getId();
        $removedId = $removedRegistration->getId();

        $this->update($beneficiary->getId(), [
            ['id' => $keptId, 'project' => $project->getId(), 'period' => $kept->getId(), 'amount' => 1000],
        ]);

        $this->em()->clear();
        self::assertNotNull($this->em()->find(RegisteredPeriods::class, $keptId));
        self::assertNull($this->em()->find(RegisteredPeriods::class, $removedId));
    }

    public function testARowAddedInTheBrowserIsInsertedAlongsideTheStoredOnes(): void
    {
        $project = $this->createProject('MSPR');
        $existing = $this->createPeriod($project);
        $added = $this->createPeriod($project, month: 9);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $existing, 1000);
        $beneficiaryId = $beneficiary->getId();

        $this->update($beneficiaryId, [
            ['id' => $registration->getId(), 'project' => $project->getId(), 'period' => $existing->getId(), 'amount' => 1000],
            // No id — the hidden input on a template-cloned row is empty.
            ['id' => null, 'project' => $project->getId(), 'period' => $added->getId(), 'amount' => 3000],
        ]);

        $this->em()->clear();
        self::assertCount(2, $this->em()->find(Beneficiary::class, $beneficiaryId)->registeredPeriods);
    }

    public function testANewRowWithNoUsablePeriodIsStillIgnored(): void
    {
        // Nothing to fall back to, and the period column is NOT NULL.
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 1000);
        $beneficiaryId = $beneficiary->getId();

        $this->update($beneficiaryId, [
            ['id' => $registration->getId(), 'project' => $project->getId(), 'period' => $period->getId(), 'amount' => 1000],
            ['id' => null, 'project' => -1, 'period' => 0, 'amount' => 3000],
        ]);

        $this->em()->clear();
        self::assertCount(1, $this->em()->find(Beneficiary::class, $beneficiaryId)->registeredPeriods);
    }

    // ---- payment methods ---------------------------------------------------------
    //
    // These stay a rebuild rather than a reconcile: the form renders all four types as
    // checkboxes unconditionally, so a submission always describes the complete desired
    // state and there is nothing the form can fail to express.

    public function testPaymentMethodsAreSavedForABeneficiaryWithNoRegisteredPeriods(): void
    {
        // The case the removed project gate used to swallow. It resolved a project from the
        // beneficiary's first registered period, skipped every row when there wasn't one,
        // and assigned that project to nothing — the mapping is commented out of the entity.
        // With no periods, the account number was deleted and never written back.
        $beneficiary = $this->createBeneficiary();
        $beneficiaryId = $beneficiary->getId();

        BeneficiaryFactory::compileEntityForUpdate([
            'id' => $beneficiaryId,
            'name' => 'No periods yet',
            'status' => Beneficiary::STATUS_NEW,
            'comment' => null,
            'school' => null,
            'createdBy' => null,
            'registeredPeriods' => [],
            'paymentMethods' => [
                ['type' => 1, 'accountNumber' => '000000000000000098', 'wireInstructions' => null],
            ],
        ], $this->em());

        $this->em()->clear();
        $stored = $this->em()->find(Beneficiary::class, $beneficiaryId);

        self::assertCount(1, $stored->paymentMethods);
        self::assertSame('000000000000000098', $stored->paymentMethods->first()->accountNumber);
    }

    public function testUncheckingEveryPaymentMethodRemovesThem(): void
    {
        // The flip side: a rebuild is only correct if an absent row really does mean the user
        // took it away. For these it does — the checkbox is the row.
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();
        $registration = $this->createRegisteredPeriod($beneficiary, $project, $period, 1000);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: 1, accountNumber: '000000000000000098');
        $beneficiaryId = $beneficiary->getId();

        BeneficiaryFactory::compileEntityForUpdate([
            'id' => $beneficiaryId,
            'name' => 'Updated',
            'status' => Beneficiary::STATUS_NEW,
            'comment' => null,
            'school' => null,
            'createdBy' => null,
            'registeredPeriods' => [
                ['id' => $registration->getId(), 'project' => $project->getId(), 'period' => $period->getId(), 'amount' => 1000],
            ],
            'paymentMethods' => [],
        ], $this->em());

        $this->em()->clear();
        self::assertCount(0, $this->em()->find(Beneficiary::class, $beneficiaryId)->paymentMethods);
    }

    /** @param array<int, array<string, mixed>> $registeredPeriods */
    private function update(int $beneficiaryId, array $registeredPeriods): void
    {
        BeneficiaryFactory::compileEntityForUpdate([
            'id' => $beneficiaryId,
            'name' => 'Updated Beneficiary',
            'status' => Beneficiary::STATUS_NEW,
            'comment' => null,
            'school' => null,
            'createdBy' => null,
            'registeredPeriods' => $registeredPeriods,
            'paymentMethods' => [],
        ], $this->em());
    }
}
