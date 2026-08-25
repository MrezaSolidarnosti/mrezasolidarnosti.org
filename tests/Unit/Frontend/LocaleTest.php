<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Frontend;

use Skeletor\Core\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Page\Repository\PageRepository;

#[CoversClass(Locale::class)]
final class LocaleTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        // detectFromRequest() reads and rewrites $_SERVER, so snapshot it.
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    // ---- parse ------------------------------------------------------------

    public function testParseReturnsTheDefaultWhenThePathHasNoPrefix(): void
    {
        self::assertSame('sr', $this->locale()->parse('/o-nama'));
    }

    public function testParseReadsAKnownNonDefaultPrefix(): void
    {
        self::assertSame('en', $this->locale()->parse('/en/o-nama'));
    }

    public function testParseFallsBackToTheDefaultForAnUnknownPrefix(): void
    {
        self::assertSame('sr', $this->locale()->parse('/de/o-nama'));
    }

    public function testParseFallsBackToTheDefaultForTheRootPath(): void
    {
        self::assertSame('sr', $this->locale()->parse('/'));
    }

    public function testAnExplicitDefaultPrefixIsNotTreatedAsALocale(): void
    {
        // '/sr/x' is a page called "sr", not the Serbian version of "/x" — the default
        // locale is served unprefixed, so its prefix is never stripped.
        $locale = $this->locale();

        self::assertSame('sr', $locale->parse('/sr/x'));
        self::assertSame('/sr/x', $locale->strip('/sr/x'));
    }

    // ---- strip ------------------------------------------------------------

    public function testStripRemovesAKnownPrefix(): void
    {
        self::assertSame('/o-nama', $this->locale()->strip('/en/o-nama'));
    }

    public function testStripLeavesAnUnprefixedPathAlone(): void
    {
        self::assertSame('/o-nama', $this->locale()->strip('/o-nama'));
    }

    public function testStrippingABarePrefixYieldsTheRoot(): void
    {
        self::assertSame('/', $this->locale()->strip('/en'));
    }

    public function testStrippingAnEmptyPathYieldsTheRoot(): void
    {
        self::assertSame('/', $this->locale()->strip(''));
    }

    // ---- localize ---------------------------------------------------------

    public function testLocalizePrefixesANonDefaultLocale(): void
    {
        self::assertSame('/en/o-nama', $this->locale()->localize('/o-nama', 'en'));
    }

    public function testLocalizeLeavesTheDefaultLocaleUnprefixed(): void
    {
        self::assertSame('/o-nama', $this->locale()->localize('/o-nama', 'sr'));
    }

    public function testLocalizeAddsTheLeadingSlashToARelativePath(): void
    {
        self::assertSame('/en/o-nama', $this->locale()->localize('o-nama', 'en'));
    }

    public function testTheLocalizedHomeHasNoTrailingSlash(): void
    {
        // Worth pinning: the English home is '/en', not '/en/'. Anything matching on the
        // prefix has to allow for both forms — global.js got this wrong and ran the whole
        // English homepage in Serbian.
        self::assertSame('/en', $this->locale()->localize('/', 'en'));
    }

    public function testLocalizeDefaultsToTheCurrentLocale(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/o-nama';
        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('/en/kontakt', $locale->localize('/kontakt'));
    }

    // ---- absolutePath (static) --------------------------------------------

    public function testAbsolutePathAddsALeadingSlashToABareSlug(): void
    {
        self::assertSame('/doniraj', Locale::absolutePath('doniraj'));
    }

    public function testAbsolutePathLeavesAlreadyAbsoluteAndExternalLinksAlone(): void
    {
        self::assertSame('', Locale::absolutePath(''));
        self::assertSame('/doniraj', Locale::absolutePath('/doniraj'));
        self::assertSame('#section', Locale::absolutePath('#section'));
        self::assertSame('//cdn.example.com/x', Locale::absolutePath('//cdn.example.com/x'));
        self::assertSame('https://example.com', Locale::absolutePath('https://example.com'));
        self::assertSame('mailto:a@b.rs', Locale::absolutePath('mailto:a@b.rs'));
    }

    // ---- localizeUrl ------------------------------------------------------

    public function testLocalizeUrlIsAPassThroughOnTheDefaultLocale(): void
    {
        $pages = $this->createMock(PageRepository::class);
        // The default locale's slugs are the authored form; nothing to look up.
        $pages->expects(self::never())->method('findTranslatedSlug');

        self::assertSame('/doniraj', $this->locale($pages)->localizeUrl('/doniraj'));
    }

    public function testLocalizeUrlTranslatesTheSlug(): void
    {
        $locale = $this->englishLocale($this->pagesReturning('donate'));

        self::assertSame('/en/donate', $locale->localizeUrl('/doniraj'));
    }

    public function testLocalizeUrlKeepsTheOriginalWhenNoTranslationExists(): void
    {
        // Better a link that resolves in the default locale than a 404.
        $locale = $this->englishLocale($this->pagesReturning(null));

        self::assertSame('/doniraj', $locale->localizeUrl('/doniraj'));
    }

    public function testLocalizeUrlPrefixesTheHomeWithoutTranslating(): void
    {
        $pages = $this->createMock(PageRepository::class);
        $pages->expects(self::never())->method('findTranslatedSlug');

        self::assertSame('/en', $this->englishLocale($pages)->localizeUrl('/'));
    }

    public function testLocalizeUrlPreservesQueryAndFragment(): void
    {
        $locale = $this->englishLocale($this->pagesReturning('donate'));

        self::assertSame('/en/donate?a=1', $locale->localizeUrl('/doniraj?a=1'));
        self::assertSame('/en/donate#dole', $locale->localizeUrl('/doniraj#dole'));
    }

    public function testLocalizeUrlLeavesExternalAndRelativeLinksAlone(): void
    {
        $locale = $this->englishLocale($this->pagesReturning('donate'));

        self::assertSame('', $locale->localizeUrl(''));
        self::assertSame('https://example.com', $locale->localizeUrl('https://example.com'));
        self::assertSame('//cdn.example.com/x', $locale->localizeUrl('//cdn.example.com/x'));
        self::assertSame('doniraj', $locale->localizeUrl('doniraj'));
    }

    public function testSlugTranslationIsMemoizedPerRequest(): void
    {
        $pages = $this->createMock(PageRepository::class);
        $pages->expects(self::once())
            ->method('findTranslatedSlug')
            ->with('doniraj', 'sr', 'en')
            ->willReturn('donate');

        $locale = $this->englishLocale($pages);

        self::assertSame('/en/donate', $locale->localizeUrl('/doniraj'));
        self::assertSame('/en/donate', $locale->localizeUrl('/doniraj'));
    }

    // ---- detectFromRequest ------------------------------------------------

    public function testDetectStripsThePrefixAndRewritesTheRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/o-nama';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('en', $locale->current());
        self::assertFalse($locale->isDefault());
        self::assertSame('/o-nama', $locale->basePath());
        // Rewritten in place so the router matches one set of language-agnostic routes.
        self::assertSame('/o-nama', $_SERVER['REQUEST_URI']);
    }

    public function testDetectKeepsTheQueryStringOnTheRewrittenUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/o-nama?page=2';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('/o-nama', $locale->basePath());
        self::assertSame('/o-nama?page=2', $_SERVER['REQUEST_URI']);
    }

    public function testDetectRecoversTheLocaleFromTheRefererOnANonGetRequest(): void
    {
        // Forms post to unprefixed action URLs, so the path alone would say "default".
        $_SERVER['REQUEST_URI'] = '/donor/login';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_REFERER'] = 'http://solidarity.local/en/logovanje';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('en', $locale->current());
    }

    public function testDetectIgnoresTheRefererOnAGetRequest(): void
    {
        $_SERVER['REQUEST_URI'] = '/o-nama';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_REFERER'] = 'http://solidarity.local/en/o-nama';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('sr', $locale->current());
    }

    public function testDetectOnTheDefaultLocaleLeavesEverythingAlone(): void
    {
        $_SERVER['REQUEST_URI'] = '/o-nama';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame('sr', $locale->current());
        self::assertTrue($locale->isDefault());
        self::assertSame('/o-nama', $locale->basePath());
    }

    // ---- alternates -------------------------------------------------------

    public function testAlternatesGiveEveryLocaleAUrlForTheCurrentPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/o-nama';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $locale = $this->locale();
        $locale->detectFromRequest();

        self::assertSame(['sr' => '/o-nama', 'en' => '/en/o-nama'], $locale->alternates());
    }

    public function testAvailableAndDefaultExposeTheConfiguredLocales(): void
    {
        $locale = $this->locale();

        self::assertSame('sr', $locale->default());
        self::assertSame(['sr', 'en'], $locale->available());
    }

    // ---- helpers ----------------------------------------------------------

    private function locale(?PageRepository $pages = null): Locale
    {
        return new Locale(
            new Config(['locales' => ['default' => 'sr', 'available' => ['sr', 'en']]]),
            $pages ?? $this->createStub(PageRepository::class),
        );
    }

    /** A Locale already resolved to 'en', which is what localizeUrl() needs to do anything. */
    private function englishLocale(?PageRepository $pages = null): Locale
    {
        $_SERVER['REQUEST_URI'] = '/en/x';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $locale = $this->locale($pages);
        $locale->detectFromRequest();

        return $locale;
    }

    private function pagesReturning(?string $slug): PageRepository
    {
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findTranslatedSlug')->willReturn($slug);

        return $pages;
    }
}
