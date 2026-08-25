<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Skeletor\ContentEditor\Contracts\BlockViewInterface;
use Solidarity\Frontend\Action\PageAction;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Frontend\Service\Session;
use Solidarity\Page\Entity\Page;
use Solidarity\Page\Repository\PageRepository;

/**
 * The CMS page dispatcher.
 *
 * Only the decision logic is covered: __invoke() ends in respond(), which renders through
 * layout::standard and would need most of bootstrap's Plates factory reconstructed — a
 * harness far more brittle than the wiring it would protect. The two methods below are
 * where this class can actually be wrong.
 */
#[CoversClass(PageAction::class)]
final class PageActionTest extends FrontendActionTestCase
{
    // ---- resolveRedirectsBasedOnSession -------------------------------------

    public function testALoggedInDonorIsSentAwayFromTheRegistrationPage(): void
    {
        // Landing on "register" while already signed in is a dead end; the donor gets
        // bounced to the page they actually wanted.
        $response = $this->action(loggedIn: true)->resolveRedirectsBasedOnSession('registracija-donatora');

        self::assertNotNull($response);
        self::assertStringEndsWith('/instrukcije-za-uplatu', $response->getHeaderLine('Location'));
    }

    public function testALoggedInDonorIsSentAwayFromLoginAndEmailConfirmationToo(): void
    {
        foreach (['logovanje', 'potvrdi-email'] as $slug) {
            self::assertNotNull(
                $this->action(loggedIn: true)->resolveRedirectsBasedOnSession($slug),
                sprintf('Expected a redirect away from "%s".', $slug),
            );
        }
    }

    public function testEveryOtherPageIsLeftAloneForALoggedInDonor(): void
    {
        // The redirect list is an allowlist of three auth pages; anything else — including
        // the donate page they were heading for — must render normally.
        self::assertNull($this->action(loggedIn: true)->resolveRedirectsBasedOnSession('doniraj'));
        self::assertNull($this->action(loggedIn: true)->resolveRedirectsBasedOnSession('kontakt'));
    }

    public function testAGuestIsNeverRedirected(): void
    {
        // Guests need those three pages; redirecting them would make signing in impossible.
        foreach (['registracija-donatora', 'logovanje', 'potvrdi-email'] as $slug) {
            self::assertNull(
                $this->action(loggedIn: false)->resolveRedirectsBasedOnSession($slug),
                sprintf('A guest must be able to reach "%s".', $slug),
            );
        }
    }

    // ---- setLocalizedSwitcher -------------------------------------------------

    public function testTheSwitcherPointsAtEachLocalesOwnSlug(): void
    {
        $page = $this->createPage('doniraj', translationGroupId: 5);
        $this->createPage('donate', translationGroupId: 5, locale: 'en');

        self::assertSame(
            ['sr' => '/doniraj', 'en' => '/en/donate'],
            $this->switcherFor($page),
        );
    }

    public function testALocaleWithNoTranslationFallsBackToItsHomepage(): void
    {
        // Better the English home than a link into a page that does not exist in English.
        $page = $this->createPage('doniraj', translationGroupId: 7);

        self::assertSame(
            ['sr' => '/doniraj', 'en' => '/en'],
            $this->switcherFor($page),
        );
    }

    public function testAPageIsAlwaysItsOwnVariantInItsOwnLocale(): void
    {
        // Even with no translationGroupId at all, the current locale must still link to
        // the page you are on rather than bouncing you home.
        $page = $this->createPage('kontakt', translationGroupId: null);

        self::assertSame('/kontakt', $this->switcherFor($page)['sr']);
    }

    public function testARootCarryingNoGroupStillOffersItsTranslation(): void
    {
        // The case that sent every reader home: a pair translated before createTranslation()
        // began stamping the source keeps a NULL group on the root, while the translation
        // points at the root's id - so there was no group to look up from the root's side.
        $root = $this->createPage('doniraj', translationGroupId: null);
        $this->createPage('donate', translationGroupId: $root->getId(), locale: 'en');

        self::assertSame(
            ['sr' => '/doniraj', 'en' => '/en/donate'],
            $this->switcherFor($root),
        );
    }

    public function testAnUngroupedPageIsOfferedUnderItsOwnSlugRatherThanTheHomepage(): void
    {
        // Two pages that were never grouped but share a slug are the same page to a reader.
        // Offering the English homepage instead loses their place for no reason - and the
        // English page is looked up rather than assumed, so this cannot link into a 404.
        $page = $this->createPage('kontakt', translationGroupId: null);
        $this->createPage('kontakt', translationGroupId: null, locale: 'en');

        self::assertSame(
            ['sr' => '/kontakt', 'en' => '/en/kontakt'],
            $this->switcherFor($page),
        );
    }

    // ---- helpers ----------------------------------------------------------------

    /**
     * setLocalizedSwitcher() is private and only writes a template global, so read the
     * result back off the Plates engine — the same reflection approach StatisticsTest
     * uses for getStats().
     *
     * @return array<string, string>
     */
    private function switcherFor(Page $page): array
    {
        $engine = $this->engine();
        $action = $this->action(loggedIn: false, engine: $engine);

        (new ReflectionMethod(PageAction::class, 'setLocalizedSwitcher'))->invoke($action, $page);

        return $engine->getData()['localeAlternates'] ?? [];
    }

    private function action(bool $loggedIn, ?\League\Plates\Engine $engine = null): PageAction
    {
        $session = $this->createStub(Session::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $session->method('isDonor')->willReturn($loggedIn);

        // Real Locale: prefixing and the "default locale is unprefixed" rule are exactly
        // what the switcher assertions are about.
        $locale = new Locale(
            new \Skeletor\Core\Config\Config(['locales' => ['default' => 'sr', 'available' => ['sr', 'en']]]),
            new PageRepository($this->em()),
        );

        return new PageAction(
            $this->logger(),
            $this->config(),
            $engine ?? $this->engine(),
            $this->navigation(),
            $this->socialLinks(),
            $session,
            new PageRepository($this->em()),
            $this->createStub(BlockViewInterface::class),
            $locale,
        );
    }
}
