<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Frontend\Action\Donor\Login;
use Solidarity\Frontend\Service\Locale;

/**
 * Requesting a magic login link.
 *
 * The security property here is that the response must be identical whether or not the
 * address is registered — otherwise the endpoint is an account-enumeration oracle, and
 * this one is unauthenticated and unrated.
 */
#[CoversClass(Login::class)]
final class LoginTest extends FrontendActionTestCase
{
    public function testAValidAddressRequestsALink(): void
    {
        $service = $this->createMock(DonorService::class);
        $service->expects(self::once())->method('requestLoginLink')->with('donor@example.com');

        $response = $this->action($service)($this->post(['email' => 'donor@example.com']), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
    }

    public function testTheAddressIsTrimmedBeforeItIsUsed(): void
    {
        // A stray space from a paste would otherwise miss the account and look like the
        // link was sent, since the response is deliberately identical either way.
        $service = $this->createMock(DonorService::class);
        $service->expects(self::once())->method('requestLoginLink')->with('donor@example.com');

        $this->action($service)($this->post(['email' => '  donor@example.com  ']), $this->emptyResponse());
    }

    public function testAnUnknownAddressLooksExactlyLikeAKnownOne(): void
    {
        // requestLoginLink() is silent for unregistered addresses by design; the action
        // must not add anything that distinguishes the two.
        $service = $this->createStub(DonorService::class);

        $known = $this->action($service)($this->post(['email' => 'known@example.com']), $this->emptyResponse());
        $unknown = $this->action($service)($this->post(['email' => 'nobody@example.com']), $this->emptyResponse());

        self::assertSame($this->decode($known), $this->decode($unknown));
        self::assertSame($known->getStatusCode(), $unknown->getStatusCode());
    }

    public function testAMalformedAddressIsRejectedWithoutRequestingALink(): void
    {
        $service = $this->createMock(DonorService::class);
        $service->expects(self::never())->method('requestLoginLink');

        $response = $this->action($service)($this->post(['email' => 'not-an-email']), $this->emptyResponse());

        self::assertFalse($this->decode($response)['success']);
        self::assertContains('Unesite ispravnu email adresu', $this->errorsFrom($response));
    }

    public function testTheRedirectIsLocalized(): void
    {
        // On /en this has to resolve to the translated slug, or the donor is bounced to a
        // Serbian page mid-login.
        $locale = $this->createMock(Locale::class);
        $locale->expects(self::once())
            ->method('localizeUrl')
            ->with('/login-link-poslat')
            ->willReturn('/en/login-link-sent');

        $response = $this->action($this->createStub(DonorService::class), $locale)(
            $this->post(['email' => 'donor@example.com']),
            $this->emptyResponse(),
        );

        self::assertSame('/en/login-link-sent', $this->decode($response)['data']['redirect']);
    }

    public function testAnEmptyBodyStillRedirects(): void
    {
        // Nothing to act on, but the page it lands on is harmless and this keeps a
        // bodyless request from producing an undefined-index fatal.
        $response = $this->action($this->createStub(DonorService::class))(
            (new \GuzzleHttp\Psr7\ServerRequest('POST', '/donor/login'))->withParsedBody([]),
            $this->emptyResponse(),
        );

        self::assertTrue($this->decode($response)['success']);
    }

    private function action(DonorService $donor, ?Locale $locale = null): Login
    {
        if ($locale === null) {
            $locale = $this->createStub(Locale::class);
            $locale->method('localizeUrl')->willReturnArgument(0);
        }

        return new Login(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $donor,
            $this->navigation(),
            $this->socialLinks(),
            $this->session(),
            $locale,
        );
    }
}
