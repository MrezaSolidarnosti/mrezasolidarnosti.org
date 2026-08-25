<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Period\Entity\Period;

#[CoversClass(Beneficiary::class)]
final class BeneficiaryTest extends TestCase
{
    public function testGetAmountForPeriodReturnsRegisteredAmount(): void
    {
        $period = $this->period(10);
        $beneficiary = $this->beneficiaryWith([
            $this->registeredPeriod($period, 3000),
        ]);

        self::assertSame(3000, $beneficiary->getAmountForPeriod($period));
    }

    public function testGetAmountForPeriodReturnsZeroWhenPeriodNotRegistered(): void
    {
        $beneficiary = $this->beneficiaryWith([
            $this->registeredPeriod($this->period(10), 3000),
        ]);

        self::assertSame(0, $beneficiary->getAmountForPeriod($this->period(99)));
    }

    public function testGetAmountForPeriodMatchesByPeriodIdNotInstance(): void
    {
        // The registered period and the queried period are different objects
        // that share the same id — the match is by id.
        $beneficiary = $this->beneficiaryWith([
            $this->registeredPeriod($this->period(10), 4200),
        ]);

        self::assertSame(4200, $beneficiary->getAmountForPeriod($this->period(10)));
    }

    public function testGetHrStatusReturnsLabelForKnownStatus(): void
    {
        self::assertSame('Ok', Beneficiary::getHrStatus(Beneficiary::STATUS_NEW));
        self::assertSame('Problem', Beneficiary::getHrStatus(Beneficiary::STATUS_PROBLEM));
        self::assertSame('Deleted', Beneficiary::getHrStatus(Beneficiary::STATUS_DELETED));
        self::assertSame('Gave up', Beneficiary::getHrStatus(Beneficiary::STATUS_GAVE_UP));
    }

    public function testEveryStatusConstantHasAHumanReadableLabel(): void
    {
        $labels = Beneficiary::getHrStatuses();

        foreach ([
            Beneficiary::STATUS_NEW,
            Beneficiary::STATUS_PROBLEM,
            Beneficiary::STATUS_DELETED,
            Beneficiary::STATUS_GAVE_UP,
        ] as $status) {
            self::assertArrayHasKey($status, $labels);
        }
    }

    /**
     * @param RegisteredPeriods[] $registeredPeriods
     */
    private function beneficiaryWith(array $registeredPeriods): Beneficiary
    {
        $beneficiary = new Beneficiary();
        $beneficiary->registeredPeriods = new ArrayCollection($registeredPeriods);

        return $beneficiary;
    }

    private function registeredPeriod(Period $period, int $amount): RegisteredPeriods
    {
        $rp = new RegisteredPeriods();
        $rp->period = $period;
        $rp->amount = $amount;

        return $rp;
    }

    private function period(int $id): Period
    {
        $period = new Period();
        $period->id = $id;

        return $period;
    }
}
