<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Beneficiary\Validator\Beneficiary as BeneficiaryValidator;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\School\Entity\School;
use Solidarity\School\Service\School as SchoolService;

#[CoversClass(BeneficiaryFilter::class)]
final class BeneficiaryFilterTest extends TestCase
{
    public function testFilterTrimsFieldsAndResolvesCreatedByFromSchoolDelegate(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter($this->postData());

        self::assertSame('John', $result['name']);          // trimmed
        self::assertSame('note', $result['comment']);        // trimmed
        self::assertSame(8, $result['createdBy']);           // the school's (only) delegate
    }

    public function testFilterParsesRegisteredPeriodsAndPaymentMethods(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter($this->postData());

        self::assertCount(1, $result['registeredPeriods']);
        self::assertSame(
            ['id' => null, 'project' => 1, 'period' => 2, 'amount' => 1000],
            $result['registeredPeriods'][0],
        );

        self::assertCount(1, $result['paymentMethods']);
        self::assertSame(2, $result['paymentMethods'][0]['type']);
        self::assertSame('wire info', $result['paymentMethods'][0]['wireInstructions']);
    }

    public function testAStoredRowKeepsItsIdSoTheSaveCanMatchItUp(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter($this->postData([
            ['id' => '17', 'project' => '1', 'period' => '2', 'amount' => '1000'],
        ]));

        self::assertSame(17, $result['registeredPeriods'][0]['id']);
    }

    public function testAStoredRowSurvivesEvenWhenItsSelectsCameBackUnusable(): void
    {
        // What a delegate posts when the registration's project is not on their assigned
        // list: the project <select> has no matching <option> and falls back to its
        // placeholder. Dropping the row here is what used to delete the registration —
        // it has to reach syncRegisteredPeriods(), which falls back to what is stored.
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter($this->postData([
            ['id' => '17', 'project' => '-1', 'period' => '', 'amount' => '1000'],
        ]));

        self::assertCount(1, $result['registeredPeriods']);
        self::assertSame(17, $result['registeredPeriods'][0]['id']);
        self::assertSame(0, $result['registeredPeriods'][0]['period']);
    }

    public function testABlankRowNobodyFilledInIsStillDiscarded(): void
    {
        // No id and no period: a row the user added with + and then left alone. Nothing to
        // preserve, and inserting it would fail on a NOT NULL period.
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter($this->postData([
            ['project' => '-1', 'period' => '', 'amount' => ''],
        ]));

        self::assertSame([], $result['registeredPeriods']);
    }

    public function testCreatedByIsNullWhenSchoolHasNoDelegate(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([]));

        $result = $filter->filter($this->postData());

        self::assertNull($result['createdBy']);
    }

    public function testADirectlyChosenDelegateIsUsedWhenThereIsNoSchool(): void
    {
        // The MSPR case. Those beneficiaries are created with school = null (MigrateLegacyMspr
        // sets it), so there is nothing to hang a delegate off and the form posts one directly.
        // The school service must not be consulted at all: resolving a null id is what returned
        // null, made createdBy null, and left the validator refusing every save.
        $schools = $this->createMock(SchoolService::class);
        $schools->expects(self::never())->method('getById');

        $filter = new BeneficiaryFilter($this->validator(valid: true), $schools);

        $result = $filter->filter(['school' => '', 'delegate' => '12'] + $this->postData());

        self::assertNull($result['school']);
        self::assertSame(12, $result['createdBy']);
    }

    public function testAPostedDelegateWhoIsNotOneOfTheSchoolsIsReplacedByTheSchoolsDelegate(): void
    {
        // With a school chosen, createdBy is only the contact and must be one of the school's
        // delegates - an outsider would be recorded against a school they cannot see.
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8]));

        $result = $filter->filter(['delegate' => '12'] + $this->postData());

        self::assertSame(8, $result['createdBy']);
    }

    public function testAPostedDelegateWhoIsOneOfTheSchoolsIsKeptAsTheContact(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([8, 12]));

        $result = $filter->filter(['delegate' => '12'] + $this->postData());

        self::assertSame(12, $result['createdBy']);
    }

    public function testWithoutAPostedDelegateTheSchoolsFirstDelegateIsTheContact(): void
    {
        // "First" by id, not by load order, so the same school always gives the same answer.
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([15, 8]));

        $result = $filter->filter($this->postData());

        self::assertSame(8, $result['createdBy']);
    }

    public function testADirectDelegateCannotStandInForASchoolWithNone(): void
    {
        // It used to: the posted delegate became createdBy, which was also what made the
        // beneficiary visible to them. Access now follows the school, so that delegate would
        // own someone they cannot see - createdBy stays null and the validator asks for a
        // school that has a delegate (the form's school search only offers those anyway).
        $filter = new BeneficiaryFilter($this->validator(valid: true), $this->schoolService([]));

        $result = $filter->filter(['delegate' => '12'] + $this->postData());

        self::assertNull($result['createdBy']);
    }

    public function testFilterThrowsWhenValidatorFails(): void
    {
        $filter = new BeneficiaryFilter($this->validator(valid: false), $this->schoolService([8]));

        $this->expectException(ValidatorException::class);

        $filter->filter($this->postData());
    }

    /**
     * @param array<int, array<string, string>>|null $registeredProjects overrides the rows
     * @return array<string, mixed>
     */
    private function postData(?array $registeredProjects = null): array
    {
        return [
            'id' => 4,
            'name' => '  John  ',
            'status' => 1,
            'comment' => '  note  ',
            'school' => 2,
            'registeredProjects' => $registeredProjects ?? [
                ['project' => '1', 'period' => '2', 'amount' => '1000'],
                ['project' => '1', 'period' => '', 'amount' => '500'], // no period, no id -> skipped
            ],
            'paymentMethods' => [
                ['type' => '2', 'wireInstructions' => '  wire info  '],
                ['type' => '', 'wireInstructions' => 'x'], // no type -> skipped
            ],
        ];
    }

    private function validator(bool $valid): BeneficiaryValidator
    {
        $validator = $this->createStub(BeneficiaryValidator::class);
        $validator->method('isValid')->willReturn($valid);

        return $validator;
    }

    /** @param int[] $delegateIds the delegates of the school the stub returns */
    private function schoolService(array $delegateIds): SchoolService
    {
        $school = new School();
        foreach ($delegateIds as $id) {
            $delegate = new Delegate();
            $delegate->id = $id;
            $school->delegates->add($delegate);
        }

        $service = $this->createStub(SchoolService::class);
        $service->method('getById')->willReturn($school);

        return $service;
    }
}
