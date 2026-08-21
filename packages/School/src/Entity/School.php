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

    // Required. Every reader already assumed so — the edit form dereferences
    // $model->type->id and the table row builder reads $school->type->name, both unguarded,
    // so a typeless row took out the whole school list rather than just itself.
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
    }

    #[ORM\ManyToOne(targetEntity: Delegate::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'delegate_id', referencedColumnName: 'id', nullable: true)]
    public ?Delegate $delegate = null;

    #[ORM\Column(name:'have_payout_priority')]
    private bool $havePayoutPriority = false;
}