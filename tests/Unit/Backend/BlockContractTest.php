<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skeletor\ContentEditor\Contracts\BlockFilterInterface;
use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Skeletor\Image\Service\Image as ImageService;
use Solidarity\Backend\Blocks\BlockViewFilters\ImageData;

/**
 * The contract every page section keeps, now that pages run on the new content editor.
 *
 * A section is three things that have to agree: a **block filter** (the save path won't
 * accept a block type that has none), a **registration** in bootstrap.php under the name the
 * editor saves, and a **template** at the path the type spells out. Any one of them missing
 * breaks the section, and none of them raises an error where it is written:
 *
 *   - no filter, or an unregistered one → BlockFilterNotFoundException when someone saves
 *   - no template                       → TemplateNotFoundException that aborts the whole page
 *   - enabled in the editor but not in PHP (or the reverse) → an "unknown block" placeholder,
 *     or a block nobody can insert
 *
 * There are 29 of these and they are individually trivial, so this asserts the shared
 * contract across all of them, discovered from disk rather than listed — a section added
 * later is covered the moment it exists.
 *
 * (Replaces BlockParserContractTest, which tested the old editor's parse() contract. Those
 * parsers were deleted when pages moved onto the new editor.)
 */
final class BlockContractTest extends TestCase
{
    private const FILTERS = APP_PATH . '/packages/Backend/src/Blocks/BlockFilters';
    private const TEMPLATES = APP_PATH . '/themes/frontend/contentEditor/app';
    private const EDITOR_CONFIG = APP_PATH . '/public/assets/backend/js/page/config.js';

    // ---- the shared contract -----------------------------------------------------------

    #[DataProvider('blockFilters')]
    public function testABlockFilterIsAPassThrough(string $class): void
    {
        // These sections carry no data the save path has to touch; the filter exists because
        // the factory rejects a block type it has no filter for. Anything that starts
        // rewriting data here changes what is stored for every page that uses the block.
        $data = ['type' => 'app/x', 'title' => 'Naslov', 'items' => ['a', 'b'], 'additionalData' => []];

        self::assertSame($data, (new $class())->filter($data));
    }

    #[DataProvider('blockFilters')]
    public function testABlockFilterSurvivesAnEmptyBlock(string $class): void
    {
        // A section dragged onto a page and not filled in yet is the normal state of a draft.
        self::assertSame([], (new $class())->filter([]));
    }

    #[DataProvider('blockFilters')]
    public function testEveryBlockFilterIsRegistered(string $class): void
    {
        // Registration is by hand in bootstrap.php. A filter class that exists but was never
        // registered fails at the moment an editor hits save, not here.
        self::assertContains(self::blockName($class), self::registeredFilters());
    }

    #[DataProvider('blockFilters')]
    public function testEveryBlockHasAFrontendTemplate(string $class): void
    {
        // The block type *is* the template path: `app/faq` -> contentEditor/app/faq.php.
        // A missing one throws mid-render and takes the rest of the page's blocks with it.
        $name = substr(self::blockName($class), strlen('app/'));

        self::assertFileExists(self::TEMPLATES . '/' . $name . '.php');
    }

    // ---- the two halves of the editor have to agree ------------------------------------

    public function testEveryBlockTheEditorCanInsertHasAFilter(): void
    {
        // Enabled in config.js but unregistered in PHP: the author can insert it, and the
        // save fails with BlockFilterNotFoundException.
        $missing = array_diff(self::enabledInEditor(), self::registeredFilters());

        self::assertSame([], array_values($missing));
    }

    public function testEveryRegisteredBlockIsEnabledInTheEditor(): void
    {
        // Registered in PHP but not in config.js: existing content loads as an inert
        // "unknown block" placeholder and nobody can insert a new one.
        $missing = array_diff(self::registeredFilters(), self::enabledInEditor());

        self::assertSame([], array_values($missing));
    }

    public function testNoBlockIsRegisteredTwice(): void
    {
        // The factory keys by name, so a second registration silently replaces the first.
        $registered = self::registeredFilters();

        self::assertSame(array_unique($registered), $registered);
    }

    public function testEveryViewFilterIsRegisteredForABlockThatExists(): void
    {
        // A view filter registered under a name no block uses never runs, and reads as
        // coverage that isn't there.
        foreach (self::registeredViewFilters() as $name) {
            self::assertContains($name, self::registeredFilters(), $name . ' has a view filter but no block');
        }
    }

    /** @return array<string, array{string}> */
    public static function viewFilterClasses(): array
    {
        $classes = [];
        foreach (glob(APP_PATH . '/packages/Backend/src/Blocks/BlockViewFilters/*.php') ?: [] as $path) {
            $class = 'Solidarity\\Backend\\Blocks\\BlockViewFilters\\' . basename($path, '.php');
            if (class_exists($class)) {
                $classes[basename($path, '.php')] = [$class];
            }
        }

        return $classes;
    }

    #[DataProvider('viewFilterClasses')]
    public function testAViewFilterImplementsTheViewFilterContract(string $class): void
    {
        self::assertTrue((new \ReflectionClass($class))->implementsInterface(BlockViewFilterInterface::class));
    }

    // ---- the one view filter with logic of its own -------------------------------------

    public function testAStoredImageIdIsResolvedIntoWhatTheTemplateNeeds(): void
    {
        // The editor stores an id and a filename; the templates also print an alt, which is
        // only in the media library. Six sections rely on this, and a null from it is
        // indistinguishable in the output from "no image chosen".
        $filter = new ImageData($this->imageService(), ['imageId' => 'alt']);

        $parsed = $filter->filter(['imageId' => 12, 'filename' => '/old/path.jpg']);

        self::assertSame('Kako funkcioniše', $parsed['alt']);
        self::assertSame('/2026/07/how-it-works.jpg', $parsed['filename'], 'the filename is refreshed too');
    }

    public function testImagesInsideAListAreResolvedRow_by_row(): void
    {
        $filter = new ImageData($this->imageService(), [], ['cards' => ['imageId' => 'alt']]);

        $parsed = $filter->filter(['cards' => [['imageId' => 12], ['imageId' => 12]]]);

        self::assertSame('Kako funkcioniše', $parsed['cards'][0]['alt']);
        self::assertSame('Kako funkcioniše', $parsed['cards'][1]['alt']);
    }

    public function testABlockWithNoImageChosenGetsAnEmptyAltRatherThanAMissingKey(): void
    {
        // Templates print this directly; a missing key is an undefined index on the page.
        $parsed = (new ImageData($this->imageService(), ['imageId' => 'alt']))->filter([]);

        self::assertSame('', $parsed['alt']);
    }

    public function testADeletedImageLeavesTheStoredValuesAlone(): void
    {
        // The media entry is gone but the page still references it: the stored filename is
        // the best guess left, so it must not be blanked.
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('getById')->willThrowException(new \RuntimeException('gone'));

        $parsed = (new ImageData($imageService, ['imageId' => 'alt']))
            ->filter(['imageId' => 99, 'filename' => '/still/here.jpg']);

        self::assertSame('/still/here.jpg', $parsed['filename']);
        self::assertSame('', $parsed['alt']);
    }

    // ---- discovery ----------------------------------------------------------------------

    public function testTheBlocksWereActuallyFound(): void
    {
        // The discovery below is path-based; if the directory moves, every parameterised
        // test above quietly runs zero times and the suite still goes green.
        self::assertGreaterThan(20, count(self::blockFilters()));
    }

    /**
     * Every BlockFilterInterface under packages/Backend/src/Blocks/BlockFilters, found on
     * disk rather than listed here — a hand-maintained list would go stale the first time a
     * section is added.
     *
     * @return array<string, array{string}>
     */
    public static function blockFilters(): array
    {
        $filters = [];
        foreach (glob(self::FILTERS . '/*.php') ?: [] as $path) {
            $class = 'Solidarity\\Backend\\Blocks\\BlockFilters\\' . basename($path, '.php');
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if (!$reflection->implementsInterface(BlockFilterInterface::class) || $reflection->isAbstract()) {
                continue;
            }
            $filters[$reflection->getShortName()] = [$class];
        }

        return $filters;
    }

    // ---- helpers -------------------------------------------------------------------------

    /** `…\BlockFilters\Faq` -> `app/faq`, the name the editor saves and bootstrap registers. */
    private static function blockName(string $class): string
    {
        return 'app/' . strtolower(substr($class, strrpos($class, '\\') + 1));
    }

    /** @return list<string> */
    private static function registeredFilters(): array
    {
        preg_match_all(
            "/registerBlockFilter\(\s*'(app\/\w+)'/",
            (string) file_get_contents(APP_PATH . '/config/bootstrap.php'),
            $matches,
        );

        return $matches[1];
    }

    /** @return list<string> */
    private static function registeredViewFilters(): array
    {
        preg_match_all(
            "/registerViewFilter\(\s*'(app\/\w+)'/",
            (string) file_get_contents(APP_PATH . '/config/bootstrap.php'),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    /** The app blocks the page editor is configured to offer. @return list<string> */
    private static function enabledInEditor(): array
    {
        preg_match_all("/'(app\/\w+)'/", (string) file_get_contents(self::EDITOR_CONFIG), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * ImageData type-hints the marker `CrudServiceInterface`, which declares no methods at
     * all, so a stub built from the declared type has no getById(). bootstrap.php passes the
     * image service, which does — stubbing what is actually injected keeps this honest about
     * the real dependency rather than the declared one.
     */
    private function imageService(): ImageService
    {
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('getById')->willReturn(
            (object) ['id' => 12, 'filename' => '/2026/07/how-it-works.jpg', 'alt' => 'Kako funkcioniše'],
        );

        return $imageService;
    }
}
