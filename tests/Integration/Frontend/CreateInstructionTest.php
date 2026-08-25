<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Frontend\Action\Donor\CreateInstruction;
use Solidarity\Frontend\Service\Locale;

/**
 * The button a donor presses to give money now.
 *
 * This is the one-time path: unlike a monthly pledge, which the cron allocates later, this
 * creates payment instructions during the request and sends the donor to the page listing
 * them. The allocation arithmetic is covered by CreateInstructionLadderTest; what is
 * asserted here is the layer the browser reaches — the login gate, what each failure looks
 * like to the page, and that a refused attempt writes nothing.
 */
#[CoversClass(CreateInstruction::class)]
final class CreateInstructionTest extends FrontendActionTestCase
{
    private const BANK = 1;

    public function testADonationCreatesInstructionsAndSendsTheDonorToThem(): void
    {
        $donor = $this->donorWhoCanGive();

        $response = $this->action($donor)($this->post($this->submission(5000)), $this->emptyResponse());
        $data = $this->decode($response);

        self::assertTrue($data['success']);
        self::assertSame('/instrukcije-za-uplatu?message=created', $data['data']['redirect']);
        self::assertGreaterThan(0, $this->instructionCountFor($donor));
    }

    public function testAnAnonymousVisitorCannotCreateAnything(): void
    {
        // The donor id comes from the session, never from the body — without one there is
        // nobody to attribute the instruction to.
        $this->donorWhoCanGive();

        $response = $this->action(null)($this->post($this->submission(5000)), $this->emptyResponse());

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame(0, $this->totalInstructions());
    }

    public function testWhenThereIsNothingToFundTheDonorIsToldWhy(): void
    {
        // NoNeedsException carries the rung of the ladder it stopped at, and the page shows
        // that text — "nothing needs funding right now" is a different answer from "your
        // payment method cannot reach anyone", and the donor needs to know which.
        $donor = $this->donorWithNobodyToGiveTo();

        $response = $this->action($donor)($this->post($this->submission(5000)), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertNotEmpty($this->errorsFrom($response));
        self::assertSame(0, $this->totalInstructions());
    }

    public function testAnInvalidSubmissionComesBackWithTheFieldErrors(): void
    {
        // The donation-data validator's messages are what the form prints next to the
        // amount; an empty errors list would leave the donor staring at a form that refuses
        // to submit and says nothing.
        $donor = $this->donorWhoCanGive();

        $response = $this->action($donor)($this->post(['project' => 1, 'payment' => []]), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame(0, $this->totalInstructions());
    }

    public function testEveryResponseCarriesAFreshToken(): void
    {
        // The donate form stays on screen after a failure, so it needs a usable token or the
        // donor's next attempt is refused for a reason that has nothing to do with them.
        $donor = $this->donorWhoCanGive();

        $success = $this->action($donor)($this->post($this->submission(5000)), $this->emptyResponse());
        $failure = $this->action($donor)($this->post(['project' => 1, 'payment' => []]), $this->emptyResponse());

        self::assertNotEmpty($this->decode($success)['data']['token']);
        self::assertNotEmpty($this->decode($failure)['data']['token']);
    }

    public function testTheRedirectIsLocalisedForTheVisitorsLanguage(): void
    {
        // An English visitor must not be dropped onto the Serbian URL; Locale::localizeUrl
        // is what prefixes it, and skipping it would 404 them after a successful donation.
        $donor = $this->donorWhoCanGive();
        $locale = $this->createStub(Locale::class);
        $locale->method('localizeUrl')->willReturnCallback(static fn (string $url): string => '/en' . $url);

        $response = $this->action($donor, $locale)($this->post($this->submission(5000)), $this->emptyResponse());

        self::assertSame('/en/instrukcije-za-uplatu?message=created', $this->decode($response)['data']['redirect']);
    }

    // ---- fixtures -----------------------------------------------------------------

    /** A donor, a processing period and a beneficiary who can be paid by bank transfer. */
    private function donorWhoCanGive(): Donor
    {
        $project = $this->createProjectWithId(1, 'MSP');
        $period = $this->createPeriod($project);
        $period->processing = true;
        $this->em()->flush();
        $project->periods->add($period);

        $beneficiary = $this->createBeneficiary('Recipient');
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 40000);

        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, amount: 5000);

        return $donor;
    }

    /** Same fixture minus anyone to give to, for the "nothing needs funding" path. */
    private function donorWithNobodyToGiveTo(): Donor
    {
        $project = $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, amount: 5000);

        return $donor;
    }

    /** @return array<string, mixed> */
    private function submission(int $amount): array
    {
        return [
            'project' => 1,
            'payment' => [self::BANK => ['value' => $amount, 'currency' => 1]],
        ];
    }

    private function instructionCountFor(Donor $donor): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM `transaction` WHERE donorId = ?',
            [$donor->getId()],
        );
    }

    private function totalInstructions(): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM `transaction`');
    }

    private function action(?Donor $donor, ?Locale $locale = null): CreateInstruction
    {
        if ($locale === null) {
            $locale = $this->createStub(Locale::class);
            $locale->method('localizeUrl')->willReturnArgument(0);
        }

        return new CreateInstruction(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $this->realDonorService(),
            $this->navigation(),
            $this->socialLinks(),
            $this->session($donor),
            $locale,
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
