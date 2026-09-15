<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Core\Login\Service\Login as LoginService;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Frontend\Action\Donor\VerifyEmail;
use Skeletor\Core\Login\Repository\MagicLinkTokenRepository;
use Solidarity\Frontend\Service\Locale;

/**
 * The magic link landing page: it authenticates the token, promotes a brand-new donor to
 * verified, logs them in, and sends them somewhere useful.
 *
 * The ordering is what matters. Verification has to be persisted before the login, and a
 * donor Donor::isActive() rejects (problem, deleted) must be bounced without ever reaching
 * the login call — a valid token for a disabled account is exactly the case where a missing
 * guard becomes an account takeover. The two unpaid-instruction flags are NOT disabled
 * accounts: they stop allocation, not access, and TRY_TO_CONTACT is lifted by the login
 * itself, since logging in is the "coming back" the flag says never happened.
 *
 * Only the POST leg is exercised. GET renders a confirmation button and spends nothing —
 * that split exists because mail clients prefetch links and were destroying single-use
 * tokens before the donor clicked.
 *
 * The branches that render a template (missing token, invalid credentials, the GET
 * confirmation page) are not covered here: respond() needs the whole theme and its layout
 * globals, which is a bigger harness than the branch is worth.
 */
#[CoversClass(VerifyEmail::class)]
final class VerifyEmailTest extends FrontendActionTestCase
{
    public function testAFirstClickVerifiesTheDonorAndSendsThemToTheWelcomePage(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_NEW);

        $response = $this->verify($donor);

        self::assertSame('/registrovani-ste', $this->redirectPath($response));
        self::assertSame(Donor::STATUS_VERIFIED, $this->reload($donor)->status);
    }

    public function testAReturningDonorGoesStraightToTheirInstructions(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);

        self::assertSame('/instrukcije-za-uplatu', $this->redirectPath($this->verify($donor)));
    }

    public function testTheDonorIsLoggedIn(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);

        $login = $this->createMock(LoginService::class);
        $login->expects(self::once())->method('login')->with($donor, 'donor');

        $this->verify($donor, login: $login);
    }

    public function testADisabledAccountIsBouncedWithoutBeingLoggedIn(): void
    {
        // A valid token for a deleted or problem account must not become a session.
        $donor = $this->createDonor(status: Donor::STATUS_DELETED);

        $login = $this->createMock(LoginService::class);
        $login->expects(self::never())->method('login');

        self::assertSame('/', $this->redirectPath($this->verify($donor, login: $login)));
    }

    public function testATryToContactDonorIsLetInAndTheFlagIsLifted(): void
    {
        // The flag means "never came back". Logging in is coming back, so the action clears
        // it on the spot and restarts the streak clock — otherwise the donor stays out of
        // allocation until the cron happens to notice a moved lastVisit.
        $donor = $this->createDonor(status: Donor::STATUS_TRY_TO_CONTACT);
        $donor->statusChangedAt = new \DateTime('-10 days');
        $this->em()->flush();

        $login = $this->createMock(LoginService::class);
        $login->expects(self::once())->method('login')->with($donor, 'donor');

        self::assertSame('/instrukcije-za-uplatu', $this->redirectPath($this->verify($donor, login: $login)));

        $reloaded = $this->reload($donor);
        self::assertSame(Donor::STATUS_VERIFIED, $reloaded->status);
        self::assertGreaterThan(new \DateTime('-1 minute'), $reloaded->statusChangedAt);
    }

    public function testAnIgnoringPaymentsDonorIsLetInButKeepsTheFlag(): void
    {
        // Shadow ban: they may log in, and only paying an instruction clears it
        // (ConfirmPayment), not merely showing up.
        $donor = $this->createDonor(status: Donor::STATUS_IGNORING_PAYMENTS);

        $login = $this->createMock(LoginService::class);
        $login->expects(self::once())->method('login')->with($donor, 'donor');

        self::assertSame('/instrukcije-za-uplatu', $this->redirectPath($this->verify($donor, login: $login)));
        self::assertSame(Donor::STATUS_IGNORING_PAYMENTS, $this->reload($donor)->status);
    }

    public function testTheRedirectIsLocalized(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);

        $locale = $this->createStub(Locale::class);
        $locale->method('localizeUrl')->willReturnCallback(
            static fn (string $url): string => '/en' . $url,
        );

        self::assertSame('/en/instrukcije-za-uplatu', $this->redirectPath($this->verify($donor, locale: $locale)));
    }

    // ---- helpers ------------------------------------------------------------

    private function verify(Donor $donor, ?LoginService $login = null, ?Locale $locale = null): ResponseInterface
    {
        $authenticator = $this->createStub(AuthenticatorRegistry::class);
        $authenticator->method('authenticate')->willReturn($donor);

        // Real repository: promoting NEW -> VERIFIED has to actually persist, which is
        // what the first test asserts against the database.
        $entities = $this->createStub(EntityRegistry::class);
        $entities->method('getRepository')->willReturn(new DonorRepository($this->em()));

        if ($locale === null) {
            $locale = $this->createStub(Locale::class);
            $locale->method('localizeUrl')->willReturnArgument(0);
        }

        $action = new VerifyEmail(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $this->createStub(DelegateService::class),
            $this->navigation(),
            $this->socialLinks(),
            $authenticator,
            $entities,
            $login ?? $this->createStub(LoginService::class),
            $this->session(),
            $locale,
            // Only read on the GET leg, to report an expired link before the donor clicks.
            // These tests drive the POST leg, where the authenticator owns the token.
            $this->createStub(MagicLinkTokenRepository::class),
        );

        // POST, not GET. Since "added two step login to prevent clients from destroying a
        // token", GET only renders a confirmation button — mail clients were prefetching the
        // link and spending the single-use token before the donor ever clicked. Everything
        // these tests assert (verify, log in, redirect) now happens on the POST.
        return $action(
            (new ServerRequest('POST', '/donor/verifyEmail'))->withParsedBody(['token' => 't']),
            $this->emptyResponse(),
        );
    }

    private function reload(Donor $donor): Donor
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->find($id);
    }
}
