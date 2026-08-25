<?php

namespace Solidarity\School\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Transaction\Entity\Round;

#[ORM\Entity]
#[ORM\Table(name: 'school_type')]
class SchoolType
{
    use Timestampable;

    /**
     * Adopts schools that arrive without a type.
     *
     * School::$type is required by the mapping, so bulk imports need somewhere to put a school
     * whose type cannot be worked out rather than dropping it — a school with no type still has
     * beneficiaries hanging off it. Same placeholder migration 20260811120000 used for the rows
     * that were already NULL; anything parked here needs an admin to give it a real type:
     *
     *   SELECT s.id, s.name FROM school s
     *   JOIN school_type t ON t.id = s.type_id WHERE t.name = 'Nepoznato';
     */
    public const PLACEHOLDER = 'Nepoznato';

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    // mappedBy names the property on the owning side, which is School::$type — it said
    // 'schoolType', a field that does not exist there, so this collection could never have
    // been loaded. Nothing reads it (the only ->schools in the app is Delegate's), so
    // correcting the name is a fix with no behaviour to change.
    #[ORM\OneToMany(targetEntity: School::class, mappedBy: 'type')]
    public Collection $schools;

    public function __construct()
    {
        $this->schools = new ArrayCollection();
    }
}