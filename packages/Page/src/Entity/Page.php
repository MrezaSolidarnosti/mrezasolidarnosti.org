<?php

namespace Solidarity\Page\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Seo;
use Skeletor\Image\Entity\Image;

#[ORM\Entity]
#[ORM\Table(name: 'page')]
#[ORM\UniqueConstraint(name: 'uniq_page_slug_language', columns: ['slug', 'languageCode'])]
class Page
{
    use \Skeletor\Core\Entity\Timestampable;
    use Seo;

    const STATUS_NEW = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_DRAFT = 2;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    public string $slug;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $blockData;

    #[ORM\ManyToOne(targetEntity: Image::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'featuredImageId', referencedColumnName: 'id', unique: false, nullable: true)]
    public ?Image $featuredImage;

    #[ORM\Column(type: Types::INTEGER)]
    public int $status;

    /**
     * Locale this page's content is written in (e.g. 'sr', 'en'); matches the
     * frontend Locale codes. Nullable so the column can be added to an existing
     * table — backfill existing rows to the default locale, then it behaves as
     * required. A page is only served on the frontend for its own locale.
     */
    #[ORM\Column(type: Types::STRING, length: 8, nullable: true)]
    public ?string $languageCode = null;

    /**
     * Links the language variants of one logical page. All translations of the
     * same page share a translationGroupId; the language switcher uses it to find
     * the sibling page (and its localized slug) in another locale.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $translationGroupId = null;

    // Defaulted rather than left uninitialised: the column is NOT NULL, and factories and
    // fixtures do `new Page()` without touching this — Doctrine then reads an uninitialised
    // typed property as null and the insert dies on the constraint. A page is public unless
    // someone ticks the box, so 0 is the right default. PHP-side only; the column is unchanged.
    #[ORM\Column(type: Types::INTEGER)]
    public int $isLoginProtected = 0;


    public static function getHrStatuses(): array
    {
        return array(
            self::STATUS_NEW => 'New',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_DRAFT => 'Draft',
        );
    }

    public static function getHrStatus($status): string
    {
        return static::getHrStatuses()[$status];
    }
}