<?php

namespace Solidarity\Educator\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;

#[ORM\Entity]
#[ORM\Index(name: 'idx_search', columns: ['month', 'year', 'type'])]
#[ORM\Index(name: 'idx_processing', columns: ['processing'])]
#[ORM\Table(name: 'period')]
class Period
{
    use Timestampable;

    public const TYPE_FIRST_HALF = 'first-half';
    public const TYPE_SECOND_HALF = 'second-half';
    public const TYPE_FULL = 'full';

    #[ORM\Column(type: Types::INTEGER)]
    public ?int $month = null;

    #[ORM\Column(type: Types::INTEGER)]
    public ?int $year = null;

    #[ORM\Column(type: Types::STRING, length: 30)]
    public ?string $type = self::TYPE_FULL;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $active = true;

    #[ORM\Column]
    public bool $processing = false;

    /**
     * @var Collection<int, Educator>
     */
    #[ORM\OneToMany(targetEntity: Educator::class, mappedBy: 'period')]
    public Collection $educators;

    public function __construct()
    {
        $this->educators = new ArrayCollection();
    }

    public function getLabel()
    {
        return sprintf('%d-%d-%s', $this->month, $this->year, $this->type);
    }

    // @todo
    public function getChoiceLabel(): string
    {
        $month = $this->date->format('M');

        $type = match ($this->getType()) {
            static::TYPE_FIRST_HALF => ' (1/2)',
            static::TYPE_SECOND_HALF => ' (2/2)',
            default => '',
        };

        return $month.$type.', '.$this->getYear();
    }
}
