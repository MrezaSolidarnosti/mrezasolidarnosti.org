<?php

namespace Solidarity\School\Entity;

use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
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

    #[ORM\ManyToOne(targetEntity: SchoolType::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', unique: false, nullable: true)]
    public ?SchoolType $type;

    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', unique: false)]
    public City $city;

    #[ORM\Column]
    private ?bool $processing = true;

    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'school')]
    private Collection $beneficiaries;

    #[ORM\ManyToOne(targetEntity: Delegate::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'delegate_id', referencedColumnName: 'id', nullable: true)]
    public ?Delegate $delegate = null;

    #[ORM\Column(name:'have_payout_priority')]
    private bool $havePayoutPriority = false;
}