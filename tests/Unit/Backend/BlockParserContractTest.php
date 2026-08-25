<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skeletor\ContentEditor\Contracts\BlockParserInterface;
use Skeletor\Core\Service\Contracts\CrudServiceInterface;
use Skeletor\Image\Service\Image as ImageService;

/**
 * The contract every page block keeps.
 *
 * There are 28 of these — a sixth of `packages/` — and they all do the same job: take the
 * raw array the content editor stored and turn it into the shape a template reads. They are
 * individually dull and collectively load-bearing: a block whose `parse()` drops a key
 * renders a blank section on the public site, and a block that omits a key entirely gives
 * the template an undefined index. Neither raises an error anywhere.
 *
 * So rather than 28 near-identical test classes, this asserts the shared contract across all
 * of them, discovered from disk. A block added later is covered the moment it exists — and
 * if it breaks any of the rules below, it fails here rather than on the page.
 *
 * No #[CoversClass]: there is no single class to name, and #[CoversNothing] would stop the
 * lines being attributed at all, which is the opposite of the point.
 */
final class BlockParserContractTest extends TestCase
{
    /** Applied to every block by getDefaultDataKeys(); the editor's layout controls. */
    private const STYLE_KEYS = [
        'blockHTMLId',
        'blockHTMLClassName',
        'blockViewMode',
        'containerMarginTop',
        'containerMarginBottom',
        'containerMarginLeft',
        'containerMarginRight',
        'containerPaddingTop',
        'containerPaddingBottom',
        'containerPaddingLeft',
        'containerPaddingRight',
    ];

    // ---- the shared contract --------------------------------------------------------

    #[DataProvider('blockParsers')]
    public function testABlockLabelsItsOutputWithItsOwnName(string $class): void
    {
        // The template is chosen by 'type'. Two blocks agreeing on it, or one returning
        // something other than its own NAME, renders the wrong section entirely.
        $parsed = $this->parse($class, []);

        self::assertSame($class::NAME, $parsed['type'] ?? null);
    }

    #[DataProvider('blockParsers')]
    public function testAnEmptyBlockIsParsedRatherThanRefused(string $class): void
    {
        // A block dragged onto a page and not filled in yet is the normal state of a draft.
        // It has to survive parsing — this runs on the public page, not in the editor.
        $parsed = $this->parse($class, []);

        self::assertArrayHasKey('type', $parsed);
    }

    #[DataProvider('blockParsers')]
    public function testABlockReturnsTheSameKeysWhetherOrNotItWasFilledIn(string $class): void
    {
        // The one that matters most. Every key a template might read has to be present with
        // a safe default even when the editor left the block empty — a key that only appears
        // once content exists is an undefined index on the live page for every empty block.
        $empty = $this->parse($class, []);
        $filled = $this->parse($class, [$class::NAME => $this->plausibleContent($empty)]);

        self::assertSame(array_keys($empty), array_keys($filled));
    }

    #[DataProvider('blockParsers')]
    public function testTheEditorsLayoutControlsSurviveParsing(string $class): void
    {
        // Margins, padding and the wrapper id/class are set per block instance in the
        // editor. Losing them here silently reverts a page's spacing to the defaults.
        $style = array_fill_keys(self::STYLE_KEYS, 'set-by-the-editor');

        $parsed = $this->parse($class, $style);

        foreach (self::STYLE_KEYS as $key) {
            self::assertSame('set-by-the-editor', $parsed[$key] ?? null, $key);
        }
    }

    #[DataProvider('blockParsers')]
    public function testAnythingElseInTheStoredDataIsDropped(string $class): void
    {
        // Block data is whatever was in the database when the block's fields last changed,
        // so it accumulates keys from older versions. parse() is the whitelist.
        $parsed = $this->parse($class, ['leftoverFromAnOlderVersion' => 'x']);

        self::assertArrayNotHasKey('leftoverFromAnOlderVersion', $parsed);
    }

    #[DataProvider('blockParsers')]
    public function testACallerCanWidenTheWhitelist(string $class): void
    {
        // The second argument is how a caller opts a key in; every block merges it with its
        // own defaults rather than replacing them.
        $parsed = $this->parse($class, ['extraKey' => 'v', 'blockHTMLId' => 'id'], ['extraKey']);

        self::assertSame('v', $parsed['extraKey'] ?? null);
        self::assertSame('id', $parsed['blockHTMLId'] ?? null, 'the defaults must survive the merge');
    }

    // ---- repeatable items ---------------------------------------------------------------

    public function testAHalfFilledRepeaterRowStillProducesAWholeItem(): void
    {
        // Cards, FAQ sections, project tiles and so on come from a repeater the editor can
        // add an empty row to. Every item parser defaults its fields with ?? '' for exactly
        // this reason; a field added later without one is an undefined index per row.
        //
        // Looped rather than data-provided because finding the repeaters means parsing each
        // block, and that needs stubbed constructor arguments a static provider cannot make.
        $checked = 0;
        foreach ($this->repeaters() as [$class, $listKey]) {
            $where = $class::NAME . '.' . $listKey;
            $parsed = $this->parse($class, [$class::NAME => [$listKey => [[], []]]]);

            self::assertCount(2, $parsed[$listKey], $where);
            self::assertNotSame([], $parsed[$listKey][0], $where . ': an empty row must still be a whole item');
            self::assertSame(
                array_keys($parsed[$listKey][0]),
                array_keys($parsed[$listKey][1]),
                $where . ': two equally empty rows must produce the same keys',
            );
            $checked++;
        }

        self::assertGreaterThan(0, $checked, 'no repeaters found — has the block shape changed?');
    }

    public function testAnEmptyRepeaterIsAnEmptyListNotAMissingOne(): void
    {
        // Templates foreach over these directly.
        foreach ($this->repeaters() as [$class, $listKey]) {
            self::assertSame([], $this->parse($class, [])[$listKey], $class::NAME . '.' . $listKey);
        }
    }

    /**
     * Every (block, list key) pair, derived from what each block produces for empty input so
     * a repeater added later is picked up on its own.
     *
     * @return list<array{string, string}>
     */
    private function repeaters(): array
    {
        $found = [];
        foreach (self::blockParsers() as [$class]) {
            foreach ($this->parse($class, []) as $key => $value) {
                if (is_array($value)) {
                    $found[] = [$class, $key];
                }
            }
        }

        return $found;
    }

    // ---- images ----------------------------------------------------------------------------

    public function testAStoredImageIdIsResolvedIntoWhatTheTemplateNeeds(): void
    {
        // The editor stores only an id; the template needs a filename and alt text. Six
        // blocks do this lookup, and a null from it is indistinguishable in the output from
        // "no image chosen" — so the populated path is worth asserting at least once.
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('getById')->willReturn(
            (object) ['id' => 12, 'filename' => 'how-it-works.jpg', 'alt' => 'Kako funkcioniše'],
        );

        $parsed = (new \Solidarity\Backend\Blocks\Howitworks\Howitworks($imageService))
            ->parse(['howitworks' => ['imageId' => 12]]);

        self::assertSame(12, $parsed['imageId']);
        self::assertSame('how-it-works.jpg', $parsed['filename']);
        self::assertSame('Kako funkcioniše', $parsed['alt']);
    }

    public function testABlockWithNoImageChosenLeavesTheImageFieldsEmptyRatherThanMissing(): void
    {
        $parsed = $this->parse(\Solidarity\Backend\Blocks\Howitworks\Howitworks::class, []);

        self::assertArrayHasKey('filename', $parsed);
        self::assertNull($parsed['imageId']);
        self::assertNull($parsed['filename']);
    }

    // ---- registration --------------------------------------------------------------------

    public function testEveryBlockOnDiskIsRegisteredWithTheFactory(): void
    {
        // Blocks are wired by hand in bootstrap.php. One that exists but was never
        // registered simply does not render — no error, no log, just a missing section.
        $bootstrap = file_get_contents(APP_PATH . '/config/bootstrap.php');
        preg_match_all('/registerBlockParser\(\s*(\w+)::NAME/', $bootstrap, $matches);
        $registered = $matches[1];

        foreach (self::blockParsers() as [$class]) {
            $shortName = substr($class, strrpos($class, '\\') + 1);
            self::assertContains($shortName, $registered, $shortName . ' is not registered in bootstrap.php');
        }
    }

    public function testNoTwoBlocksClaimTheSameName(): void
    {
        $names = [];
        foreach (self::blockParsers() as [$class]) {
            $names[] = $class::NAME;
        }

        self::assertSame(array_unique($names), $names);
    }

    public function testTheBlocksWereActuallyFound(): void
    {
        // The discovery below is path-based; if the directory moves, every parameterised
        // test above quietly runs zero times and the suite still goes green.
        self::assertGreaterThan(20, count(self::blockParsers()));
    }

    // ---- discovery ----------------------------------------------------------------------------

    /**
     * Every BlockParserInterface under packages/Backend/src/Blocks, found on disk rather than
     * listed here — a hand-maintained list would go stale the first time a block is added.
     *
     * @return array<string, array{string}>
     */
    public static function blockParsers(): array
    {
        $parsers = [];
        foreach (glob(APP_PATH . '/packages/Backend/src/Blocks/*/*.php') ?: [] as $path) {
            $class = sprintf(
                'Solidarity\Backend\Blocks\%s\%s',
                basename(dirname($path)),
                basename($path, '.php'),
            );
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            // The ViewFilters live alongside the parsers and implement a different contract.
            if (!$reflection->implementsInterface(BlockParserInterface::class) || $reflection->isAbstract()) {
                continue;
            }
            $parsers[$reflection->getShortName()] = [$class];
        }

        return $parsers;
    }

    // ---- helpers -------------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @param string[]             $customDataKeys
     * @return array<string, mixed>
     */
    private function parse(string $class, array $data, array $customDataKeys = []): array
    {
        return $this->instantiate($class)->parse($data, $customDataKeys);
    }

    /**
     * Several blocks take an image service to resolve `imageId` into a filename. Nothing here
     * depends on what it returns, so every constructor argument is stubbed by its type.
     */
    private function instantiate(string $class): BlockParserInterface
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $arguments[] = $type instanceof \ReflectionNamedType && !$type->isBuiltin()
                ? $this->createStub($this->collaboratorFor($type->getName()))
                : null;
        }

        return new $class(...$arguments);
    }

    /**
     * `CrudServiceInterface` declares **no methods at all** — it is a marker interface — but
     * every block that takes one calls `getById()` on it. bootstrap.php passes the image
     * service, which has that method, so it works in production; a stub built from the
     * declared type does not, and the block dies on an undefined method.
     *
     * Stubbing what the container actually injects keeps this test honest about the real
     * dependency rather than the declared one. Narrowing the hints in the six blocks would
     * be the better fix, but that is a change to shipping code, not to this test.
     */
    private function collaboratorFor(string $declaredType): string
    {
        return $declaredType === CrudServiceInterface::class ? ImageService::class : $declaredType;
    }

    /**
     * Content shaped like whatever the block produced for empty input: a value for each
     * scalar key, one empty row for each list. Enough to tell "always returns this key" from
     * "returns it once there is content".
     *
     * @param array<string, mixed> $emptyResult
     * @return array<string, mixed>
     */
    private function plausibleContent(array $emptyResult): array
    {
        $content = [];
        foreach ($emptyResult as $key => $value) {
            if ($key === 'type') {
                continue;
            }
            $content[$key] = is_array($value) ? [[]] : 'content';
        }

        return $content;
    }
}
