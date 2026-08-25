<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Page\Entity\Page;
use Solidarity\Page\Repository\PageRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;

/**
 * How a URL becomes a page, and how the language switcher finds its counterpart.
 *
 * Every public page on the site is resolved through here, so the queries below decide what
 * a visitor sees and — via `status` and `languageCode` — what they must not: a draft, or the
 * Serbian text on an English URL.
 *
 * Pages relate through `translationGroupId`. The variants of one logical page share it, and
 * the source page may either carry the group id or *be* the group root, which is why the
 * lookup matches `translationGroupId = :gid OR id = :gid`.
 */
#[CoversClass(PageRepository::class)]
final class PageRepositoryTest extends IntegrationTestCase
{
    // ---- resolving a URL ----------------------------------------------------------

    public function testAPublishedPageIsFoundBySlugWithinItsLocale(): void
    {
        $page = $this->createPage('doniraj', locale: 'sr');

        self::assertSame($page->getId(), $this->repo()->findPublishedBySlugAndLocale('doniraj', 'sr')?->getId());
    }

    public function testTheSameSlugInAnotherLocaleIsADifferentPage(): void
    {
        // The unique key is (slug, languageCode), so one slug can legitimately exist twice.
        // Matching on slug alone would serve whichever row came back first.
        $group = 1;
        $serbian = $this->createPage('kontakt', $group, 'sr');
        $english = $this->createPage('kontakt', $group, 'en');

        self::assertSame($serbian->getId(), $this->repo()->findPublishedBySlugAndLocale('kontakt', 'sr')?->getId());
        self::assertSame($english->getId(), $this->repo()->findPublishedBySlugAndLocale('kontakt', 'en')?->getId());
    }

    public function testADraftIsNotServedToVisitors(): void
    {
        $this->createPage('u-pripremi', locale: 'sr', status: Page::STATUS_DRAFT);

        self::assertNull($this->repo()->findPublishedBySlugAndLocale('u-pripremi', 'sr'));
    }

    public function testAnUnknownSlugResolvesToNothingRatherThanFailing(): void
    {
        // This is what turns into the 404; an exception here would be a 500 instead.
        self::assertNull($this->repo()->findPublishedBySlugAndLocale('no-such-page', 'sr'));
    }

    public function testTheHomepageIsJustTheSlugHomepage(): void
    {
        $home = $this->createPage('homepage', locale: 'sr');
        $this->createPage('homepage', locale: 'en');

        self::assertSame($home->getId(), $this->repo()->findPublishedHomeByLocale('sr')?->getId());
    }

    public function testALocaleWithNoHomepageYetReturnsNothing(): void
    {
        // The Index action leans on this: it falls back to the default locale's homepage so
        // home never 404s while a translation is still being written.
        $this->createPage('homepage', locale: 'sr');

        self::assertNull($this->repo()->findPublishedHomeByLocale('en'));
    }

    // ---- the language switcher -------------------------------------------------------

    public function testTheSwitcherMapsEachLocaleToItsOwnSlug(): void
    {
        // The whole point of the group: /doniraj and /en/donate are the same page, and the
        // switcher has to offer the localized slug rather than repeating the current one.
        $group = 1;
        $this->createPage('doniraj', $group, 'sr');
        $this->createPage('donate', $group, 'en');

        self::assertSame(['sr' => 'doniraj', 'en' => 'donate'], $this->repo()->getLocalizedSlugs($group));
    }

    public function testAGroupRootCarryingNoGroupStillReachesItsTranslations(): void
    {
        // createTranslation() stamps the source, but pages translated before it did keep a
        // NULL group while their translations point at their id. Looking outwards from such
        // a root there is no group to look up, so the switcher used to offer the homepage.
        $orphanRoot = $this->createPage('doniraj', null, 'sr');
        $this->createPage('donate', $orphanRoot->getId(), 'en');

        $slugs = $this->repo()->getLocalizedSlugs($orphanRoot->translationGroupId, $orphanRoot->getId());
        ksort($slugs);

        self::assertSame(['en' => 'donate', 'sr' => 'doniraj'], $slugs);
    }

    public function testATranslationLooksBackAndFindsTheRootItPointsAt(): void
    {
        // The other direction of the same pair: the group id resolves to the root's id, which
        // matches no translationGroupId because the root carries none - so it has to be matched
        // on the id as well, or the English page offers the homepage as its Serbian sibling.
        $orphanRoot = $this->createPage('doniraj', null, 'sr');
        $translation = $this->createPage('donate', $orphanRoot->getId(), 'en');

        $slugs = $this->repo()->getLocalizedSlugs($translation->translationGroupId, $translation->getId());
        ksort($slugs);

        self::assertSame(['en' => 'donate', 'sr' => 'doniraj'], $slugs);
    }

    public function testAPageOutsideAnyGroupOffersNoAlternatives(): void
    {
        self::assertSame([], $this->repo()->getLocalizedSlugs(null));
    }

    public function testAnUnpublishedTranslationIsNotOfferedAsAnAlternative(): void
    {
        // Otherwise the switcher links to a page that then 404s.
        $group = 2;
        $this->createPage('doniraj', $group, 'sr');
        $this->createPage('donate', $group, 'en', status: Page::STATUS_DRAFT);

        self::assertSame(['sr' => 'doniraj'], $this->repo()->getLocalizedSlugs($group));
    }

    // ---- translating a link ------------------------------------------------------------

    public function testASlugIsTranslatedIntoItsCounterpart(): void
    {
        // Drives localizeUrl(): menus are authored with default-locale slugs, and each one
        // has to become the target locale's own slug rather than a prefixed Serbian word.
        $source = $this->createPage('doniraj', locale: 'sr');
        $this->createPage('donate', $source->getId(), 'en');

        self::assertSame('donate', $this->repo()->findTranslatedSlug('doniraj', 'sr', 'en'));
    }

    public function testTranslatingBackFromTheGroupMemberFindsTheRoot(): void
    {
        // The reverse direction, which is the case the `OR p.id = :gid` arm exists for: the
        // English page carries the group id, the Serbian one *is* it.
        $source = $this->createPage('doniraj', locale: 'sr');
        $this->createPage('donate', $source->getId(), 'en');

        self::assertSame('doniraj', $this->repo()->findTranslatedSlug('donate', 'en', 'sr'));
    }

    public function testAPageWithNoTranslationYetTranslatesToNothing(): void
    {
        // The caller falls back to the untranslated URL rather than linking somewhere wrong.
        $this->createPage('doniraj', locale: 'sr');

        self::assertNull($this->repo()->findTranslatedSlug('doniraj', 'sr', 'en'));
    }

    public function testASlugThatDoesNotExistInTheSourceLocaleTranslatesToNothing(): void
    {
        self::assertNull($this->repo()->findTranslatedSlug('no-such-page', 'sr', 'en'));
    }

    // ---- creating a translation ----------------------------------------------------------

    public function testATranslationCopiesTheSourceAndJoinsItsGroup(): void
    {
        $source = $this->createPage('doniraj', locale: 'sr');

        $translation = $this->repo()->createTranslation($source->getId());

        self::assertNotNull($translation);
        self::assertSame($source->getId(), $translation->translationGroupId);
        self::assertSame('en', $translation->languageCode);
        self::assertSame($source->title, $translation->title);
        self::assertSame($source->slug, $translation->slug, 'the slug is copied for the editor to localise');
    }

    public function testTheSourceJoinsTheGroupSoTheTwoCanLinkToEachOther(): void
    {
        // The end-to-end version of the fix: make a translation the way the dashboard does,
        // and the switcher can then get from either page to the other. Before, the source
        // kept a NULL group and both directions fell back to the homepage.
        $source = $this->createPage('doniraj', locale: 'sr');

        $this->repo()->createTranslation($source->getId());

        $slugs = $this->repo()->getLocalizedSlugs($source->translationGroupId);

        self::assertSame($source->getId(), $source->translationGroupId);
        self::assertSame(['sr', 'en'], array_keys($slugs));
    }

    public function testATranslationJoinsTheGroupTheSourceIsAlreadyIn(): void
    {
        // Stamping the source's id unconditionally would put the new page in a group of its
        // own and leave the existing locales behind — the group has to survive a second
        // translation, not be replaced by one.
        $existingGroup = 3;
        $source = $this->createPage('doniraj', $existingGroup, 'sr');

        $translation = $this->repo()->createTranslation($source->getId());

        self::assertSame($existingGroup, $source->translationGroupId);
        self::assertSame($existingGroup, $translation->translationGroupId);
    }

    public function testTranslatingAPageThatIsNotThereIsRefusedQuietly(): void
    {
        self::assertNull($this->repo()->createTranslation(999999));
    }

    // A second translation of the same page is rejected by the (slug, languageCode) unique
    // key — which is right, since two English variants would leave the switcher choosing
    // between them arbitrarily. It is deliberately not tested here: the duplicate surfaces
    // from inside a flush, and a failed flush closes the EntityManager for the whole
    // process. The harness rebuilds it, but provoking that on purpose is a poor trade for
    // one assertion. The controller-level consequence (a useless generic error message) is
    // recorded in operations.md instead.

    private function repo(): PageRepository
    {
        return new PageRepository($this->em());
    }
}
