<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Login\Service\Login as LoginService;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Frontend\Action\Donor\VerifyEmail;
use Solidarity\Frontend\Service\Locale;

/**
 * The magic link landing page: it authenticates the token, promotes a brand-new donor to
 * verified, logs them in, and sends them somewhere useful.
 *
 * The ordering is what matters. Verification has to be persisted before the login, and a
 * donor whose status is neither NEW nor VERIFIED (problem, deleted) must be bounced
 * without ever reaching the login call — a valid token for a disabled account is exactly
 * the case where a missing guard becomes an account takeover.
 *
 * The two branches that render a template (missing token, invalid credentials) are not
 * covered here: respond() needs the whole theme and its layout globals, which is a bigger
 * harness than the branch is worth.
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
        );

        return $action(
            (new ServerRequest('GET', '/donor/verifyEmail?token=t'))->withQueryParams(['token' => 't']),
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
