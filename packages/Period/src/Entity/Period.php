<?php

namespace Solidarity\Period\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Transaction\Entity\Project;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uq_period_project', columns: ['month', 'year', 'type', 'project_id'])]
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

    // Not nullable, in the database or here: 0 is how "no per-period override" is spelled,
    // and a ?int invited the filter to write null into a NOT NULL column.
    #[ORM\Column(type: Types::INTEGER)]
    public int $maxAmount = 0;

    #[ORM\Column(type: Types::STRING, length: 30)]
    public ?string $type = self::TYPE_FULL;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $active = true;

    #[ORM\Column]
    public bool $processing = false;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'period')]
    public Collection $beneficiaries;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'periods')]
    #[ORM\JoinColumn(nullable: false)]
    public Project $project;

    public function __construct()
    {
        $this->beneficiaries = new ArrayCollection();
    }

    public function getLabel()
    {
        return sprintf('%s-%d-%d-%s', $this->project->code, $this->month, $this->year, $this->type);
    }
}
