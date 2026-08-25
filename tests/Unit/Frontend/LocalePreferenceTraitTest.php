<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Frontend;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Solidarity\Frontend\Action\LocalePreferenceTrait;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Frontend\Service\Session;
use Solidarity\Page\Repository\PageRepository;

/**
 * The language switcher's memory.
 *
 * Two things make this worth pinning. It issues redirects, so a wrong condition is an
 * infinite loop rather than a cosmetic bug — the guard is that it only ever fires on a
 * default-locale URL and always targets a non-default one. And it must not trap a user
 * who deliberately navigates back to Serbian, which is why only an explicit switch ever
 * records a preference.
 */
#[CoversTrait(LocalePreferenceTrait::class)]
final class LocalePreferenceTraitTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $get = [];

    protected function setUp(): void
    {
        $this->get = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
    }

    // ---- an explicit switch -------------------------------------------------

    public function testAnExplicitSwitchIsRememberedAndTheMarkerIsStripped(): void
    {
        $_GET['setLocale'] = 'en';

        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('setPreferredLocale')->with('en');

        $locale = $this->locale(isDefault: false, current: 'en', basePath: '/o-nama');
        $locale->method('localize')->willReturn('/en/o-nama');

        // Bounces to the clean URL so ?setLocale never sticks in the address bar or in
        // anything the user copies out of it.
        self::assertSame('/en/o-nama', $this->locationOf($this->resolve($locale, $session, null, 'o-nama')));
    }

    public function testAnUnknownLocaleInTheQueryIsIgnored(): void
    {
        $_GET['setLocale'] = 'de';

        $session = $this->createMock(Session::class);
        $session->expects(self::never())->method('setPreferredLocale');
        $session->method('getPreferredLocale')->willReturn(null);

        self::assertNull($this->resolve($this->locale(isDefault: true), $session, null, 'o-nama'));
    }

    // ---- honouring a stored preference ---------------------------------------

    public function testALandingOnTheDefaultLocaleIsRedirectedToThePreferredOne(): void
    {
        $session = $this->session('en');
        $locale = $this->locale(isDefault: true);
        $locale->method('localize')->willReturn('/en/donate');

        self::assertSame(
            '/en/donate',
            $this->locationOf($this->resolve($locale, $session, 'donate', 'doniraj')),
        );
    }

    public function testTheHomepageRedirectsToTheLocalizedRoot(): void
    {
        // A null slug is the homepage, which has no slug to translate — prefix only.
        $pages = $this->createMock(PageRepository::class);
        $pages->expects(self::never())->method('findTranslatedSlug');

        $locale = $this->locale(isDefault: true);
        $locale->method('localize')->willReturn('/en');

        self::assertSame(
            '/en',
            $this->locationOf($this->resolve($locale, $this->session('en'), null, null, $pages)),
        );
    }

    public function testAPageWithNoTranslationStaysPut(): void
    {
        // Redirecting to a locale where the page does not exist would turn a working
        // Serbian page into a 404 for anyone who once clicked the switcher.
        self::assertNull(
            $this->resolve($this->locale(isDefault: true), $this->session('en'), null, 'doniraj'),
        );
    }

    // ---- when it must stay out of the way -------------------------------------

    public function testNothingHappensWhenAlreadyOnANonDefaultLocale(): void
    {
        // The redirect only ever fires on default-locale URLs; this is the half of the
        // guard that makes a loop impossible.
        self::assertNull(
            $this->resolve($this->locale(isDefault: false), $this->session('en'), 'donate', 'doniraj'),
        );
    }

    public function testNothingHappensWithoutAStoredPreference(): void
    {
        self::assertNull(
            $this->resolve($this->locale(isDefault: true), $this->session(null), 'donate', 'doniraj'),
        );
    }

    public function testPreferringTheDefaultLocaleLetsTheUserBrowseItFreely(): void
    {
        // Choosing Serbian in the switcher stores 'sr'; if that still redirected, the
        // user could never get back to the Serbian site.
        self::assertNull(
            $this->resolve($this->locale(isDefault: true), $this->session('sr'), 'donate', 'doniraj'),
        );
    }

    // ---- helpers ----------------------------------------------------------------

    private function resolve(
        Locale $locale,
        Session $session,
        ?string $translatedSlug,
        ?string $currentSlug,
        ?PageRepository $pages = null,
    ): ?Response {
        if ($pages === null) {
            $pages = $this->createStub(PageRepository::class);
            $pages->method('findTranslatedSlug')->willReturn($translatedSlug);
        }

        // The trait expects an action exposing locale/session/pageRepository and a
        // redirect(); this is the smallest thing that satisfies it.
        $action = new class ($locale, $session, $pages) {
            use LocalePreferenceTrait;

            public function __construct(
                public Locale $locale,
                public Session $session,
                public PageRepository $pageRepository,
            ) {
            }

            public function resolve(?string $currentSlug): ?Response
            {
                return $this->resolveLocalePreference($currentSlug);
            }

            protected function redirect(string $url): Response
            {
                return new Response(302, ['Location' => $url]);
            }
        };

        return $action->resolve($currentSlug);
    }

    private function locale(bool $isDefault, string $current = 'sr', string $basePath = '/'): Locale
    {
        // A stub, not a mock: nothing here asserts on how Locale is called, and a mock
        // with no expectations is just a stub that PHPUnit warns about.
        $locale = $this->createStub(Locale::class);
        $locale->method('default')->willReturn('sr');
        $locale->method('available')->willReturn(['sr', 'en']);
        $locale->method('isDefault')->willReturn($isDefault);
        $locale->method('current')->willReturn($current);
        $locale->method('basePath')->willReturn($basePath);

        return $locale;
    }

    private function session(?string $preferred): Session
    {
        $session = $this->createStub(Session::class);
        $session->method('getPreferredLocale')->willReturn($preferred);

        return $session;
    }

    private function locationOf(?Response $response): ?string
    {
        self::assertNotNull($response, 'Expected a redirect.');

        return $response->getHeaderLine('Location');
    }
}
