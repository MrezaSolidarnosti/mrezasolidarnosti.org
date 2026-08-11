<?php

namespace Solidarity\Post\Entity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Seo;
use Skeletor\Core\Entity\Timestampable;
use Skeletor\Image\Entity\Image;
#[ORM\Entity]
#[ORM\Index(name: 'ft_post_search', columns: ['title', 'shortDescription'], flags: ['fulltext'])]
#[ORM\Table(name: 'post')]
class Post
{
    use Timestampable;
    use Seo;

    const int STATUS_PUBLISHED = 1;
    const int STATUS_DRAFT = 2;

    const int STATUS_PENDING = 3;
    const int STATUS_SCHEDULED = 4;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
    public string $title;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
    public string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $shortDescription;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $blockData;

    #[ORM\Column(type: Types::INTEGER)]
    public int $status;

    #[ORM\ManyToOne(targetEntity: Image::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'featuredImageId', referencedColumnName: 'id', unique: false, nullable: true)]
    public ?Image $featuredImage;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTime $publishAt = null;

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SCHEDULED => 'Scheduled',
        ];
    }

    public static function getStatusHR(int $status): string
    {
        return self::getStatuses()[$status];
    }
}