<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\Login\Service\MagicLinkService;
use Skeletor\Translator\Service\Translator;
use Skeletor\User\Service\Session as UserSession;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as ProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Frontend\Action\Donor\UpdateProfileData;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * The donor editing their own name.
 *
 * Small surface, one property worth guarding hard: the record being written is chosen by
 * the session, never by the request. The action assigns $data['id'] itself, so a posted
 * id is overwritten rather than trusted — without that, any donor could rename any other.
 */
#[CoversClass(UpdateProfileData::class)]
final class UpdateProfileDataTest extends FrontendActionTestCase
{
    public function testADonorCanChangeTheirName(): void
    {
        $donor = $this->createDonor();

        $response = $this->action($donor)(
            $this->post(['firstName' => 'Petar', 'lastName' => 'Petrović']),
            $this->emptyResponse(),
        );

        self::assertTrue($this->decode($response)['success']);

        $donor = $this->reload($donor);
        self::assertSame('Petar', $donor->firstName);
        self::assertSame('Petrović', $donor->lastName);
    }

    public function testAPostedIdIsIgnoredInFavourOfTheSession(): void
    {
        // The IDOR guard. Posting someone else's id must rename you, not them.
        $me = $this->createDonor(email: 'me@example.com');
        $victim = $this->createDonor(email: 'victim@example.com');

        $this->action($me)(
            $this->post(['id' => $victim->getId(), 'firstName' => 'Nova', 'lastName' => 'Imena']),
            $this->emptyResponse(),
        );

        self::assertSame('Nova', $this->reload($me)->firstName);
        self::assertSame('First', $this->reload($victim)->firstName);
    }

    public function testAGuestCannotChangeAnything(): void
    {
        $donor = $this->createDonor();

        $response = $this->action(null)(
            $this->post(['firstName' => 'Petar', 'lastName' => 'Petrović']),
            $this->emptyResponse(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('First', $this->reload($donor)->firstName);
    }

    // ---- validation --------------------------------------------------------

    public function testABlankNameIsRejected(): void
    {
        $donor = $this->createDonor();

        $response = $this->action($donor)(
            $this->post(['firstName' => '   ', 'lastName' => 'Petrović']),
            $this->emptyResponse(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertContains('First name is required', $this->errorsFrom($response));
    }

    public function testASingleCharacterNameIsRejected(): void
    {
        $donor = $this->createDonor();

        $response = $this->action($donor)(
            $this->post(['firstName' => 'P', 'lastName' => 'Petrović']),
            $this->emptyResponse(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertContains('First name must be at least 2 characters long', $this->errorsFrom($response));
    }

    public function testBothNamesAreChecked(): void
    {
        $donor = $this->createDonor();

        $response = $this->action($donor)(
            $this->post(['firstName' => 'Petar', 'lastName' => '']),
            $this->emptyResponse(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertContains('Last name is required', $this->errorsFrom($response));
    }

    public function testARejectedUpdateLeavesTheStoredNameAlone(): void
    {
        $donor = $this->createDonor();

        $this->action($donor)($this->post(['firstName' => 'P', 'lastName' => 'P']), $this->emptyResponse());

        self::assertSame('First', $this->reload($donor)->firstName);
    }

    public function testAFreshTokenComesBackOnFailureToo(): void
    {
        // The profile form stays on the page after a rejection, so it needs a usable
        // token or the donor's corrected second attempt fails for a different reason.
        $donor = $this->createDonor();

        $response = $this->action($donor)($this->post(['firstName' => 'P', 'lastName' => 'P']), $this->emptyResponse());

        self::assertNotEmpty($this->decode($response)['data']['token'] ?? '');
    }

    // ---- helpers ------------------------------------------------------------

    private function reload(Donor $donor): Donor
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->find($id);
    }

    private function action(?Donor $donor): UpdateProfileData
    {
        $em = $this->em();

        return new UpdateProfileData(
            $this->logger(),
            $this->config(),
            $this->engine(),
            new DonorService(
                new DonorRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                $this->createStub(DonorFilter::class),
                $this->createStub(Mailer::class),
                $this->createStub(ProjectService::class),
                $this->createStub(MagicLinkService::class),
                // Real: the filter and validator are the behaviour under test.
                new ProfileDataFilter(),
                new ProfileDataValidator(new CsrfTrueStub()),
                new DonationDataValidator(new CsrfTrueStub()),
                new DonationDataFilter(),
                $this->createStub(TransactionService::class),
                $this->createStub(QrCode::class),
                $this->createStub(Translator::class),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $this->navigation(),
            $this->socialLinks(),
            $this->session($donor),
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
