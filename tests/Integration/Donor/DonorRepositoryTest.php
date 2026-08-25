<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(DonorRepository::class)]
final class DonorRepositoryTest extends IntegrationTestCase
{
    private function repo(): DonorRepository
    {
        return new DonorRepository($this->em());
    }

    public function testReturnsActiveVerifiedAndNewDonorsForProject(): void
    {
        $project = $this->createProject('MSPR');

        $verified = $this->donorForProject($project, Donor::STATUS_VERIFIED);
        $new = $this->donorForProject($project, Donor::STATUS_NEW);

        $ids = $this->donorIds($this->repo()->getDonorsByProject($project));

        self::assertContains($verified->getId(), $ids);
        self::assertContains($new->getId(), $ids);
        self::assertCount(2, $ids);
    }

    public function testExcludesNewDonorBelongingToAnotherProject(): void
    {
        $project = $this->createProject('MSPR');
        $otherProject = $this->createProject('MSP');

        // A NEW donor on a different project must NOT leak into this project's list.
        $this->donorForProject($otherProject, Donor::STATUS_NEW);

        self::assertSame([], $this->donorIds($this->repo()->getDonorsByProject($project)));
    }

    public function testExcludesInactiveDonor(): void
    {
        $project = $this->createProject('MSPR');

        $donor = $this->donorForProject($project, Donor::STATUS_NEW);
        $donor->isActive = '0';
        $this->em()->flush();

        self::assertSame([], $this->donorIds($this->repo()->getDonorsByProject($project)));
    }

    public function testExcludesProblemAndDeletedDonors(): void
    {
        $project = $this->createProject('MSPR');

        $this->donorForProject($project, Donor::STATUS_PROBLEM);
        $this->donorForProject($project, Donor::STATUS_DELETED);

        self::assertSame([], $this->donorIds($this->repo()->getDonorsByProject($project)));
    }

    // ---- touchLastVisit ----------------------------------------------------

    public function testTouchLastVisitStampsTheDonor(): void
    {
        $donor = $this->createDonor();
        self::assertNull($donor->lastVisit);

        $this->repo()->touchLastVisit($donor->getId());

        self::assertNotNull($this->reloadDonor($donor)->lastVisit);
    }

    public function testTouchLastVisitLeavesEveryOtherDonorAlone(): void
    {
        // The guard clause is "id = :id AND (lastVisit IS NULL OR lastVisit < :threshold)".
        // Without the parentheses that reads as "(id AND null) OR stale", which stamps the
        // whole table on every authenticated request. This is that test.
        $target = $this->createDonor();
        $bystander = $this->createDonor();

        $this->repo()->touchLastVisit($target->getId());

        self::assertNotNull($this->reloadDonor($target)->lastVisit);
        self::assertNull($this->reloadDonor($bystander)->lastVisit);
    }

    public function testTouchLastVisitIsThrottled(): void
    {
        // It runs on every page view; one write per donor per window is the whole point.
        $donor = $this->createDonor();
        $this->repo()->touchLastVisit($donor->getId());
        $first = $this->reloadDonor($donor)->lastVisit;

        $this->backdateLastVisit($donor, '-1 minute');
        $this->repo()->touchLastVisit($donor->getId(), throttleSeconds: 300);

        // One minute ago is still inside a five minute window, so nothing was rewritten.
        self::assertEquals(
            (new \DateTime('-1 minute'))->format('Y-m-d H:i'),
            $this->reloadDonor($donor)->lastVisit->format('Y-m-d H:i'),
        );
        self::assertNotNull($first);
    }

    public function testTouchLastVisitWritesAgainOnceTheWindowHasPassed(): void
    {
        $donor = $this->createDonor();
        $this->backdateLastVisit($donor, '-1 hour');

        $this->repo()->touchLastVisit($donor->getId(), throttleSeconds: 300);

        self::assertSame(
            (new \DateTime())->format('Y-m-d H:i'),
            $this->reloadDonor($donor)->lastVisit->format('Y-m-d H:i'),
        );
    }

    // ---- updateDonationData (the monthly pledge) ---------------------------

    public function testUpdateDonationDataSavesTheMonthlyPledge(): void
    {
        $project = $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->repo()->updateDonationData($this->pledge($donor, 1, [1 => ['amount' => 5000, 'currency' => 1]]));

        $donor = $this->reloadDonor($donor);
        self::assertCount(1, $donor->paymentMethods);
        self::assertSame(5000, $donor->paymentMethods[0]->amount);
        self::assertSame($project->getId(), $donor->paymentMethods[0]->project->getId());
        // This endpoint is only reachable from the monthly button, so it never stores 0.
        self::assertSame(1, $donor->paymentMethods[0]->monthly);
    }

    public function testUpdateDonationDataLinksTheDonorToTheProject(): void
    {
        // donor_project is what getDonorsByProject joins on, so without it the cron
        // never sees the donor no matter what they pledged.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->repo()->updateDonationData($this->pledge($donor, 1, [1 => ['amount' => 5000, 'currency' => 1]]));

        self::assertCount(1, $this->reloadDonor($donor)->projects);
    }

    public function testChoosingBothDirectionsPledgesToEachProject(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $this->createProjectWithId(2, 'MSPR');
        $donor = $this->createDonor();

        $this->repo()->updateDonationData($this->pledge($donor, -1, [1 => ['amount' => 5000, 'currency' => 1]]));

        $donor = $this->reloadDonor($donor);
        self::assertCount(2, $donor->projects);
        // The pledged amount is per project, not split between them.
        self::assertCount(2, $donor->paymentMethods);
    }

    public function testSavingAgainReplacesTheEarlierPledgeRatherThanAddingToIt(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->repo()->updateDonationData($this->pledge($donor, 1, [1 => ['amount' => 5000, 'currency' => 1]]));
        $this->repo()->updateDonationData($this->pledge($donor, 1, [2 => ['amount' => 100, 'currency' => 2]]));

        $donor = $this->reloadDonor($donor);
        self::assertCount(1, $donor->paymentMethods);
        self::assertSame(2, $donor->paymentMethods[0]->type);
        self::assertSame(100, $donor->paymentMethods[0]->amount);
    }

    public function testSavingWithNoPaymentMethodsWipesThePledge(): void
    {
        // Documenting current behaviour, not endorsing it: the donation validator accepts
        // an empty selection, and this method clears everything before writing nothing
        // back. A donor who submits the form with no method ticked loses their pledge and
        // drops out of the cron entirely, with a success message.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();
        $this->repo()->updateDonationData($this->pledge($donor, 1, [1 => ['amount' => 5000, 'currency' => 1]]));

        $this->repo()->updateDonationData($this->pledge($donor, 1, []));

        $donor = $this->reloadDonor($donor);
        self::assertCount(0, $donor->paymentMethods);
        self::assertCount(1, $donor->projects);
    }

    public function testAnUnknownProjectIsSkippedSilently(): void
    {
        // Also current behaviour worth pinning: nothing is written, but the earlier pledge
        // has already been deleted by then, so the donor is left with nothing.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();
        $this->repo()->updateDonationData($this->pledge($donor, 1, [1 => ['amount' => 5000, 'currency' => 1]]));

        $this->repo()->updateDonationData($this->pledge($donor, 999, [1 => ['amount' => 5000, 'currency' => 1]]));

        $donor = $this->reloadDonor($donor);
        self::assertCount(0, $donor->paymentMethods);
        self::assertCount(0, $donor->projects);
    }

    /** @param array<int, array{amount: int, currency: int}> $paymentData */
    private function pledge(Donor $donor, int $projectId, array $paymentData): array
    {
        return [
            'donorId' => $donor->getId(),
            'project' => $projectId,
            'paymentData' => $paymentData,
        ];
    }

    private function backdateLastVisit(Donor $donor, string $when): void
    {
        $this->em()->getConnection()->executeStatement(
            'UPDATE `donor` SET lastVisit = :dt WHERE id = :id',
            ['dt' => (new \DateTime($when))->format('Y-m-d H:i:s'), 'id' => $donor->getId()],
        );
    }

    /**
     * clear() + find(), not refresh(): touchLastVisit writes through DQL and
     * updateDonationData mutates collections the in-memory donor never sees, and refresh()
     * does not reliably reload associations. Detaching everything is the only way to be
     * sure an assertion is reading the database rather than the identity map.
     */
    private function reloadDonor(Donor $donor): Donor
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->find($id);
    }

    private function donorForProject($project, int $status): Donor
    {
        $donor = $this->createDonor(status: $status);
        $this->linkDonorToProject($donor, $project);

        return $donor;
    }

    /**
     * @param Donor[] $donors
     * @return int[]
     */
    private function donorIds(array $donors): array
    {
        return array_map(fn (Donor $d) => $d->getId(), $donors);
    }
}
