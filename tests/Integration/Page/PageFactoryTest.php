<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use Skeletor\ContentEditor\Contracts\ContentEditorParserInterface;
use Solidarity\Page\Entity\Page;
use Solidarity\Page\Factory\PageFactory;
use Solidarity\Page\Filter\Page as PageFilter;
use Solidarity\Page\Repository\PageRepository;
use Solidarity\Page\Validator\Page as PageValidator;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Support\AssertsFieldCoverage;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Skeletor\Core\Security\Csrf;

/**
 * Posted page data, through the filter and factory, into the database.
 *
 * See PeriodFactoryTest for why the trip starts at $postData: a field dropped by the filter
 * never reaches the factory, so a factory-only test passes while the data is being discarded.
 */
#[CoversClass(PageFactory::class)]
#[CoversClass(PageFilter::class)]
final class PageFactoryTest extends IntegrationTestCase
{
    use AssertsFieldCoverage;

    /** Fields the page form posts, and what this test posts for each. */
    private const WRITABLE = [
        'title' => 'O nama',
        'slug' => 'o-nama',
        'status' => Page::STATUS_PUBLISHED,
        'seoTitle' => 'O nama | Mreza solidarnosti',
        'seoDescription' => 'Ko smo mi',
        'isLoginProtected' => false,
        'languageCode' => 'sr',
    ];

    /**
     * Where the stored value differs from the posted one, because something casts.
     *
     * The filter turns the checkbox into a bool, but `isLoginProtected` is an INTEGER column
     * behind an `int` property — so false goes in and 0 comes back. EmailList's `isActive`
     * is the mirror image: a BOOLEAN column fed an int.
     */
    private const STORED = [
        'isLoginProtected' => 0,
    ];

    /** Fields the page form does not post, with the reason. */
    private const IGNORED = [
        'blockData' => 'built by the content editor, asserted separately',
        'featuredImage' => 'relation resolved from a posted featuredImageId, asserted separately',
        'seoImage' => 'relation resolved from a posted seoImageId, asserted separately',
        // Not an oversight: there is no description input on themes/admin/page/form.php, and
        // PageFactory never assigns it. Its only writer is createTranslation(), which copies
        // the source page's value.
        'description' => 'no input on the page form; only ever copied by PageRepository::createTranslation()',
        'translationGroupId' => 'assigned when a page is translated, not by the form — see PageRepositoryTest',
    ];

    public function testEveryMappedFieldIsAccountedFor(): void
    {
        $this->assertEveryMappedFieldIsAccountedFor(Page::class, self::WRITABLE, self::IGNORED);
    }

    public function testAPostedPageIsStoredWithEveryFieldItCarried(): void
    {
        $id = PageFactory::compileEntityForCreate($this->filter(), $this->em());

        $this->em()->clear();
        $stored = $this->em()->find(Page::class, $id);

        foreach (self::WRITABLE as $field => $posted) {
            $expected = self::STORED[$field] ?? $posted;
            self::assertSame($expected, $stored->$field, $field . ' did not survive the round trip');
        }
    }

    public function testAPageWithNoImagesChosenIsStoredWithoutThem(): void
    {
        // The filter posts '' for an unchosen image and the factory looks it up anyway, so
        // this pins that an empty id resolves to null rather than exploding or to some
        // arbitrary first row.
        $id = PageFactory::compileEntityForCreate($this->filter(), $this->em());

        $this->em()->clear();
        $stored = $this->em()->find(Page::class, $id);

        self::assertNull($stored->featuredImage);
        self::assertNull($stored->seoImage);
    }

    public function testTheSlugIsDerivedFromTheTitleWhenLeftBlank(): void
    {
        $id = PageFactory::compileEntityForCreate($this->filter(['slug' => '', 'title' => 'Kako Doniram']), $this->em());

        $this->em()->clear();

        self::assertSame('kako-doniram', $this->em()->find(Page::class, $id)->slug);
    }

    public function testAnExplicitSlugIsStillSlugified(): void
    {
        // Whatever an editor types goes through the same slugify, so a space or a capital
        // cannot produce a URL that will not match on the way back in.
        $id = PageFactory::compileEntityForCreate($this->filter(['slug' => 'Kako Doniram']), $this->em());

        $this->em()->clear();

        self::assertSame('kako-doniram', $this->em()->find(Page::class, $id)->slug);
    }

    public function testAPageIsNotLoginProtectedUnlessTheBoxWasTicked(): void
    {
        // The checkbox posts the literal 'on' when ticked and is absent otherwise, so
        // anything else has to read as false rather than as truthy.
        $unticked = PageFactory::compileEntityForCreate($this->filter(), $this->em());
        $ticked = PageFactory::compileEntityForCreate(
            $this->filter(['isLoginProtected' => 'on', 'slug' => 'zasticena']),
            $this->em(),
        );

        $this->em()->clear();

        // 0 and 1, not false and true: the column is an integer even though the filter
        // hands the factory a bool.
        self::assertSame(0, $this->em()->find(Page::class, $unticked)->isLoginProtected);
        self::assertSame(1, $this->em()->find(Page::class, $ticked)->isLoginProtected);
    }

    /**
     * Runs $postData through the real filter.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function filter(array $overrides = []): array
    {
        $parser = $this->createStub(ContentEditorParserInterface::class);
        $parser->method('parse')->willReturn([]);

        $filter = new PageFilter(
            new PageValidator(new CsrfTrueStub(), new PageRepository($this->em())),
            $parser,
        );

        // Overrides first: `+` keeps the left-hand key.
        return $filter->filter($overrides + self::WRITABLE + [
            'id' => null,
            'featuredImageId' => '',
            'seoImageId' => '',
            Csrf::TOKEN_NAME => 'token',
        ]);
    }
}
