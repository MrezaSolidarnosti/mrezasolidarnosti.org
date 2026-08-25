<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transliterator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Transliterator\Service\Transliterator;

#[CoversClass(Transliterator::class)]
final class TransliteratorTest extends TestCase
{
    private Transliterator $transliterator;

    protected function setUp(): void
    {
        $this->transliterator = new Transliterator();
    }

    public function testTransliteratesCyrillicToLatin(): void
    {
        self::assertSame('Petar', $this->transliterator->transliterate('Петар'));
        self::assertSame('Mreža', $this->transliterator->transliterate('Мрежа'));
    }

    public function testTransliteratesDigraphs(): void
    {
        // љ -> lj, њ -> nj, џ -> dž
        self::assertSame('ljnjdž', $this->transliterator->transliterate('љњџ'));
    }

    public function testLeavesLatinTextUnchanged(): void
    {
        self::assertSame('Already latin', $this->transliterator->transliterate('Already latin'));
    }

    public function testEmptyStringReturnedAsIs(): void
    {
        self::assertSame('', $this->transliterator->transliterate(''));
    }

    public function testIsCyrillicDetectsCyrillicCharacters(): void
    {
        self::assertTrue($this->transliterator->isCyrillic('Петар'));
        self::assertFalse($this->transliterator->isCyrillic('Petar'));
        self::assertFalse($this->transliterator->isCyrillic('12345'));
    }
}
