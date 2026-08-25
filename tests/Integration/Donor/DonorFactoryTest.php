<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Donor\Factory\DonorFactory;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(DonorFactory::class)]
final class DonorFactoryTest extends IntegrationTestCase
{
    public function testCreatePersistsDonorWithProjectsAndPaymentMethods(): void
    {
        $project = $this->createProject('MSPR');

        $id = DonorFactory::compileEntityForCreate([
            'id' => null,
            'email' => 'factory-donor@example.com',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'wantsToDonateTo' => Donor::DONATE_TO_ALL,
            'comment' => null,
            'isActive' => 1,
            'status' => Donor::STATUS_VERIFIED,
            'projects' => [$project->getId()],
            'paymentMethods' => [
                ['project' => $project->getId(), 'type' => 1, 'monthly' => 0, 'amount' => 5000, 'currency' => 1],
            ],
        ], $this->em());

        $this->em()->clear();
        $donor = $this->em()->find(Donor::class, $id);

        self::assertSame('factory-donor@example.com', $donor->email);
        self::assertSame(Donor::STATUS_VERIFIED, $donor->status);
        self::assertSame(Donor::DONATE_TO_ALL, $donor->wantsToDonateTo);

        self::assertCount(1, $donor->projects);
        self::assertSame($project->getId(), $donor->projects->first()->getId());

        self::assertCount(1, $donor->paymentMethods);
        self::assertSame(5000, $donor->paymentMethods->first()->amount);
        self::assertSame(1, $donor->paymentMethods->first()->type);
    }

    public function testUpdateReplacesProjectsAndPaymentMethods(): void
    {
        $projectA = $this->createProject('MSPR');
        $projectB = $this->createProject('MSP');

        $donor = $this->createDonor();
        $this->linkDonorToProject($donor, $projectA);
        $this->createDonorPaymentMethod($donor, $projectA, type: 1, amount: 1000);
        $donorId = $donor->getId();

        DonorFactory::compileEntityForUpdate([
            'id' => $donorId,
            'email' => 'updated@example.com',
            'firstName' => 'Grace',
            'lastName' => 'Hopper',
            'wantsToDonateTo' => Donor::DONATE_TO_ALL,
            'comment' => null,
            'isActive' => 1,
            'status' => Donor::STATUS_VERIFIED,
            'projects' => [$projectB->getId()],
            'paymentMethods' => [
                ['project' => $projectB->getId(), 'type' => 2, 'monthly' => 1, 'amount' => 200, 'currency' => 2],
            ],
        ], $this->em());

        $this->em()->clear();
        $donor = $this->em()->find(Donor::class, $donorId);

        self::assertSame('updated@example.com', $donor->email);

        // projectA replaced by projectB
        self::assertCount(1, $donor->projects);
        self::assertSame($projectB->getId(), $donor->projects->first()->getId());

        // old payment method removed, new one created
        self::assertCount(1, $donor->paymentMethods);
        self::assertSame(200, $donor->paymentMethods->first()->amount);
        self::assertSame(2, $donor->paymentMethods->first()->type);
    }
}
