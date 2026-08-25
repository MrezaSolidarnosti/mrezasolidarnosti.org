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
use Solidarity\Donor\Validator\Donor as DonorValidator;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Frontend\Action\Donor\Register;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * Donor self-registration.
 *
 * The action overrides five fields on the way in — isActive, wantsToDonateTo, comment,
 * projects and status — so whatever the form posts for those is ignored. That is the
 * security boundary: without it, a crafted request could register itself pre-verified.
 */
#[CoversClass(Register::class)]
final class RegisterTest extends FrontendActionTestCase
{
    public function testARegistrationCreatesTheDonor(): void
    {
        $response = $this->action()($this->post($this->form()), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);

        $donor = $this->findDonor('new@example.com');
        self::assertNotNull($donor);
        self::assertSame('Petar', $donor->firstName);
    }

    public function testANewRegistrationIsNeverAlreadyVerified(): void
    {
        // status is forced to NEW regardless of what was posted; the only way to become
        // VERIFIED is clicking the emailed magic link.
        $this->action()($this->post($this->form(['status' => Donor::STATUS_VERIFIED])), $this->emptyResponse());

        self::assertSame(Donor::STATUS_NEW, $this->findDonor('new@example.com')?->status);
    }

    public function testAPostedProjectListIsIgnored(): void
    {
        // Pledges are made later on the donate page; accepting them here would let a
        // registration insert itself into the cron's donor set before verifying an email.
        $this->action()($this->post($this->form(['projects' => [1, 2]])), $this->emptyResponse());

        self::assertCount(0, $this->findDonor('new@example.com')->projects);
    }

    public function testTheVerificationEmailIsSent(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())
            ->method('sendDonorRegisteredMail')
            ->with('new@example.com', 'Petar Petrović', self::anything());

        $this->action(mailer: $mailer)($this->post($this->form()), $this->emptyResponse());
    }

    public function testTheDonorIsSentToTheConfirmEmailPage(): void
    {
        $locale = $this->createStub(Locale::class);
        $locale->method('localizeUrl')->willReturnCallback(static fn (string $u): string => '/en' . $u);

        $response = $this->action(locale: $locale)($this->post($this->form()), $this->emptyResponse());

        self::assertSame('/en/potvrdi-email', $this->decode($response)['data']['redirect']);
    }

    // ---- rejections -----------------------------------------------------------

    public function testRegisteringAnExistingEmailIsRejected(): void
    {
        $this->createDonor(email: 'taken@example.com');

        $response = $this->action()($this->post($this->form(['email' => 'taken@example.com'])), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertContains('A donor with this email address already exists.', $this->errorsFrom($response));
    }

    public function testADuplicateRegistrationSendsNoSecondEmail(): void
    {
        // Otherwise the endpoint becomes a way to mail an arbitrary address repeatedly.
        $this->createDonor(email: 'taken@example.com');

        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::never())->method('sendDonorRegisteredMail');

        $this->action(mailer: $mailer)(
            $this->post($this->form(['email' => 'taken@example.com'])),
            $this->emptyResponse(),
        );
    }

    public function testAnInvalidSubmissionIsRejectedWithReasons(): void
    {
        $response = $this->action()($this->post($this->form(['email' => 'not-an-email'])), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
        self::assertNotEmpty($this->errorsFrom($response));
        self::assertNull($this->findDonor('not-an-email'));
    }

    public function testAnEmptyBodyCreatesNothing(): void
    {
        $response = $this->action()(
            (new \GuzzleHttp\Psr7\ServerRequest('POST', '/donor/register'))->withParsedBody([]),
            $this->emptyResponse(),
        );

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(0, $this->em()->getRepository(Donor::class)->findAll());
    }

    // ---- helpers ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'email' => 'new@example.com',
            'firstName' => 'Petar',
            'lastName' => 'Petrović',
        ];
    }

    private function findDonor(string $email): ?Donor
    {
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->findOneBy(['email' => $email]);
    }

    private function action(?Mailer $mailer = null, ?Locale $locale = null): Register
    {
        $em = $this->em();

        if ($locale === null) {
            $locale = $this->createStub(Locale::class);
            $locale->method('localizeUrl')->willReturnArgument(0);
        }

        $magicLink = $this->createStub(MagicLinkService::class);
        $magicLink->method('requestMagicLink')->willReturn('token');

        return new Register(
            $this->logger(),
            $this->config(),
            $this->engine(),
            new DonorService(
                new DonorRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                // Real: the field overrides and the duplicate check are what is under test.
                new DonorFilter(new DonorValidator(new CsrfTrueStub())),
                $mailer ?? $this->createStub(Mailer::class),
                $this->createStub(ProjectService::class),
                $magicLink,
                $this->createStub(ProfileDataFilter::class),
                $this->createStub(ProfileDataValidator::class),
                new DonationDataValidator(new CsrfTrueStub()),
                new DonationDataFilter(),
                $this->createStub(TransactionService::class),
                $this->createStub(QrCode::class),
                $this->createStub(Translator::class),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            $this->navigation(),
            $this->socialLinks(),
            $this->session(),
            $locale,
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
