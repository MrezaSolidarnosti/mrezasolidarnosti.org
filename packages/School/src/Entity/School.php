<?php

namespace Solidarity\School\Entity;

use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;

#[ORM\Entity]
#[ORM\Table(name: 'school')]
class School
{
    use Timestampable;

    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $name;

    // Required by the mapping rather than by every reader remembering to guard: the edit form
    // dereferences $model->type->id and the table row builder reads $school->type->name, both
    // unguarded, so one typeless row used to break the whole /school/view/ listing. The column
    // is already NOT NULL — see migration 20260811120000_make_school_type_required.
    #[ORM\ManyToOne(targetEntity: SchoolType::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', unique: false, nullable: false)]
    public SchoolType $type;

    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', unique: false)]
    public City $city;

    #[ORM\Column]
    private ?bool $processing = true;

    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'school')]
    private Collection $beneficiaries;

    public function __construct()
    {
        // Every other entity initialises its collections here, so `new School()` is safe
        // for factories and fixtures without waiting for Doctrine to hydrate.
        $this->beneficiaries = new ArrayCollection();
        $this->delegates = new ArrayCollection();
    }

    public function hasDelegate(int $delegateId): bool
    {
        foreach ($this->delegates as $delegate) {
            if ($delegate->getId() === $delegateId) {
                return true;
            }
        }

        return false;
    }

    // Several delegates can share a school, all at the same level: the point is to split the
    // work of a big school, not to rank people. Delegate::$schools is the owning side (the
    // delegate form is where assignments are edited), so nothing written here is persisted.
    // A school with no delegates is a normal state - the beneficiary form's school search
    // filters on `d.id => not_null` precisely because such schools exist.
    #[ORM\ManyToMany(targetEntity: Delegate::class, mappedBy: 'schools')]
    public Collection $delegates;

    #[ORM\Column(name:'have_payout_priority')]
    private bool $havePayoutPriority = false;
}