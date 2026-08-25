<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Skeletor\ContentEditor\Contracts\BlockViewInterface;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Frontend\Service\LocalizingBlockView;

/**
 * CMS block templates echo their link fields raw, so a link authored in the admin as
 * "doniraj" or "/doniraj" has to be normalised and locale-swapped before it reaches the
 * template. The decorator recognises link fields purely by key name, which makes the
 * matching rule itself worth pinning: too loose and it rewrites prose, too tight and a
 * link silently keeps the Serbian slug on an English page.
 */
#[CoversClass(LocalizingBlockView::class)]
final class LocalizingBlockViewTest extends TestCase
{
    public function testARelativeLinkIsMadeAbsoluteEvenOnTheDefaultLocale(): void
    {
        // Without the leading slash, "doniraj" on /en/kontakt resolves to /en/doniraj on
        // one page and /doniraj on another — the link's meaning depends on where it is.
        self::assertSame(
            ['buttonUrl' => '/doniraj'],
            $this->processed(['buttonUrl' => 'doniraj'], isDefault: true),
        );
    }

    public function testExternalAndAnchorLinksAreLeftAlone(): void
    {
        $data = [
            'buttonUrl' => 'https://example.com',
            'linkUrl' => '//cdn.example.com/x',
            'href' => '#section',
            'mailUrl' => 'mailto:info@mrezasolidarnosti.org',
        ];

        self::assertSame($data, $this->processed($data, isDefault: true));
    }

    public function testOnlyKeysThatNameALinkAreTouched(): void
    {
        // The rule is "key ends in url, link or href", case-insensitive.
        $processed = $this->processed([
            'buttonUrl' => 'doniraj',
            'linkUrl' => 'doniraj',
            'buttonLink' => 'doniraj',
            'href' => 'doniraj',
            'HREF' => 'doniraj',
            'title' => 'doniraj',        // prose, must not gain a slash
            'urlencode' => 'doniraj',    // "url" is a prefix here, not a suffix
        ], isDefault: true);

        self::assertSame('/doniraj', $processed['buttonUrl']);
        self::assertSame('/doniraj', $processed['linkUrl']);
        self::assertSame('/doniraj', $processed['buttonLink']);
        self::assertSame('/doniraj', $processed['href']);
        self::assertSame('/doniraj', $processed['HREF']);
        self::assertSame('doniraj', $processed['title']);
        self::assertSame('doniraj', $processed['urlencode']);
    }

    public function testNestedBlockDataIsWalked(): void
    {
        // Blocks nest: ctabanner holds a buttons[] array, projectsdisplay holds projects[].
        $processed = $this->processed([
            'buttons' => [
                ['buttonTitle' => 'Doniraj', 'buttonUrl' => 'doniraj'],
                ['buttonTitle' => 'FAQ', 'buttonUrl' => 'faq'],
            ],
        ], isDefault: true);

        self::assertSame('/doniraj', $processed['buttons'][0]['buttonUrl']);
        self::assertSame('/faq', $processed['buttons'][1]['buttonUrl']);
        self::assertSame('Doniraj', $processed['buttons'][0]['buttonTitle']);
    }

    public function testNonStringValuesSurviveUntouched(): void
    {
        $data = ['imageId' => 7, 'buttonUrl' => null, 'visible' => true];

        self::assertSame($data, $this->processed($data, isDefault: true));
    }

    public function testOnANonDefaultLocaleLinksGoThroughLocalizeUrl(): void
    {
        $locale = $this->createMock(Locale::class);
        $locale->method('isDefault')->willReturn(false);
        $locale->expects(self::once())
            ->method('localizeUrl')
            ->with('/doniraj')          // already absolute by the time it gets here
            ->willReturn('/en/donate');

        self::assertSame(
            ['buttonUrl' => '/en/donate'],
            $this->processedWith($locale, ['buttonUrl' => 'doniraj']),
        );
    }

    public function testOnTheDefaultLocaleLocalizeUrlIsNeverCalled(): void
    {
        // Default-locale slugs are the authored form; a lookup per link would be waste.
        $locale = $this->createMock(Locale::class);
        $locale->method('isDefault')->willReturn(true);
        $locale->expects(self::never())->method('localizeUrl');

        $this->processedWith($locale, ['buttonUrl' => '/doniraj']);
    }

    public function testRegisterViewFilterIsHandedToTheInnerView(): void
    {
        // The decorator has to stay transparent, or every block filter silently stops
        // being registered and the whole CMS renders with unfiltered data.
        $filter = $this->createStub(BlockViewFilterInterface::class);

        $inner = $this->createMock(BlockViewInterface::class);
        $inner->expects(self::once())->method('registerViewFilter')->with('donate', $filter);

        $locale = $this->createStub(Locale::class);
        (new LocalizingBlockView($inner, $locale))->registerViewFilter('donate', $filter);
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed> the data as the inner view received it
     */
    private function processed(array $data, bool $isDefault): array
    {
        $locale = $this->createStub(Locale::class);
        $locale->method('isDefault')->willReturn($isDefault);
        $locale->method('localizeUrl')->willReturnArgument(0);

        return $this->processedWith($locale, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function processedWith(Locale $locale, array $data): array
    {
        $seen = [];
        $inner = $this->createStub(BlockViewInterface::class);
        $inner->method('getView')->willReturnCallback(function (array $passed) use (&$seen): string {
            $seen = $passed;

            return '';
        });

        (new LocalizingBlockView($inner, $locale))->getView($data);

        return $seen;
    }
}
