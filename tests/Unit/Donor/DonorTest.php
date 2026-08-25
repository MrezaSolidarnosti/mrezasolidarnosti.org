<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Donor;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Transaction\Entity\Project;

#[CoversClass(Donor::class)]
final class DonorTest extends TestCase
{
    public function testGetAmountForProjectReturnsAmountOfMatchingPaymentMethod(): void
    {
        $project = $this->project(1);
        $donor = $this->donorWith([
            $this->paymentMethod($project, PaymentMethod::TYPE_BANK_TRANSFER, 5000),
        ]);

        self::assertSame(5000, $donor->getAmountForProject($project));
    }

    public function testGetAmountForProjectReturnsZeroWhenNoPaymentMethodMatches(): void
    {
        $donor = $this->donorWith([
            $this->paymentMethod($this->project(1), PaymentMethod::TYPE_BANK_TRANSFER, 5000),
        ]);

        self::assertSame(0, $donor->getAmountForProject($this->project(2)));
    }

    public function testGetPledgedAmountMatchesBothProjectAndType(): void
    {
        $project = $this->project(1);
        $bank = $this->paymentMethod($project, PaymentMethod::TYPE_BANK_TRANSFER, 5000);
        $wire = $this->paymentMethod($project, PaymentMethod::TYPE_WIRE_TRANSFER, 200);
        $donor = $this->donorWith([$bank, $wire]);

        self::assertSame(200, $donor->getPledgedAmountForProjectAndPaymentType($project, $wire));
    }

    public function testGetPledgedAmountReturnsZeroWhenTypeDiffers(): void
    {
        $project = $this->project(1);
        $bank = $this->paymentMethod($project, PaymentMethod::TYPE_BANK_TRANSFER, 5000);
        $donor = $this->donorWith([$bank]);

        $wireFilter = $this->paymentMethod($project, PaymentMethod::TYPE_WIRE_TRANSFER, 0);

        self::assertSame(0, $donor->getPledgedAmountForProjectAndPaymentType($project, $wireFilter));
    }

    public function testGetPaymentMethodsForProjectReturnsOnlyMatchingMethods(): void
    {
        $projectA = $this->project(1);
        $projectB = $this->project(2);
        $a1 = $this->paymentMethod($projectA, PaymentMethod::TYPE_BANK_TRANSFER, 5000);
        $a2 = $this->paymentMethod($projectA, PaymentMethod::TYPE_WIRE_TRANSFER, 200);
        $b1 = $this->paymentMethod($projectB, PaymentMethod::TYPE_BANK_TRANSFER, 1000);
        $donor = $this->donorWith([$a1, $a2, $b1]);

        $methods = $donor->getPaymentMethodsForProject($projectA);

        self::assertCount(2, $methods);
        self::assertContains($a1, $methods);
        self::assertContains($a2, $methods);
        self::assertNotContains($b1, $methods);
    }

    public function testGetHrStatusReturnsLabelForKnownStatus(): void
    {
        self::assertSame('New', Donor::getHrStatus(Donor::STATUS_NEW));
        self::assertSame('Potvrdjen email', Donor::getHrStatus(Donor::STATUS_VERIFIED));
        self::assertSame('Problem', Donor::getHrStatus(Donor::STATUS_PROBLEM));
        self::assertSame('Obrisan', Donor::getHrStatus(Donor::STATUS_DELETED));
    }

    public function testEveryStatusConstantHasAHumanReadableLabel(): void
    {
        $labels = Donor::getHrStatuses();

        foreach ([
            Donor::STATUS_NEW,
            Donor::STATUS_VERIFIED,
            Donor::STATUS_PROBLEM,
            Donor::STATUS_DELETED,
        ] as $status) {
            self::assertArrayHasKey($status, $labels);
        }
    }

    public function testGetHrDonationOptionReturnsLabelForKnownOption(): void
    {
        self::assertSame('Svima', Donor::getHrDonationOption(Donor::DONATE_TO_ALL));
        self::assertSame('Prosveti', Donor::getHrDonationOption(Donor::DONATE_TO_SCHOOL));
        self::assertSame('Univerzitetima', Donor::getHrDonationOption(Donor::DONATE_TO_UNI));
    }

    /**
     * @param PaymentMethod[] $paymentMethods
     */
    private function donorWith(array $paymentMethods): Donor
    {
        $donor = new Donor();
        $donor->paymentMethods = new ArrayCollection($paymentMethods);

        return $donor;
    }

    private function paymentMethod(Project $project, int $type, int $amount): PaymentMethod
    {
        $pm = new PaymentMethod();
        $pm->project = $project;
        $pm->type = $type;
        $pm->amount = $amount;

        return $pm;
    }

    private function project(int $id): Project
    {
        $project = new Project();
        $project->id = $id;

        return $project;
    }
}
