<?php

namespace Solidarity\Beneficiary\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Period\Entity\Period;
use Solidarity\School\Entity\School;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

#[ORM\Entity]
#[ORM\Table(name: 'beneficiary')]
class Beneficiary
{
    use Timestampable;

    public const MONTHLY_LIMIT = 240000;

    public const STATUS_NEW = 1;
    public const STATUS_DELETED = 2;
    public const STATUS_GAVE_UP = 4;
    public const STATUS_PROBLEM = 7;

    // @TODO not needed?
    public const TYPE_EDUCATOR = 1;
    public const TYPE_REPRESSED_PERSON = 2;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;
//    #[ORM\Column(type: Types::INTEGER)]
//    public int $type;
    #[ORM\Column(type: Types::INTEGER)]
    public int $status;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    public ?string $comment;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'beneficiaries')]
    #[ORM\JoinColumn(name: 'school_id', referencedColumnName: 'id', unique: false, nullable: true)]
    public ?School $school;

    #[ORM\ManyToOne(inversedBy: 'beneficiaries')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Delegate $createdBy = null;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'beneficiary')]
    public Collection $transactions;

    #[ORM\OneToMany(targetEntity: RegisteredPeriods::class, mappedBy: 'beneficiary')]
    public Collection $registeredPeriods;

    #[ORM\OneToMany(targetEntity: PaymentMethod::class, mappedBy: 'beneficiary')]
    public Collection $paymentMethods;

    public function getAmountForPeriod(Period $period): int
    {
        foreach ($this->registeredPeriods as $rp) {
            if ($rp->period->getId() === $period->getId()) {
                return $rp->amount;
            }
        }
        return 0;
    }

    public static function getHrStatuses(): array
    {
        return [
            self::STATUS_NEW => 'Ok',
            self::STATUS_PROBLEM => 'Problem',
            self::STATUS_DELETED => 'Deleted',
        ];
    }

    public static function getHrStatus($status): string
    {
        return static::getHrStatuses()[$status];
    }

    public static function getHrTypes(): array
    {
        return [
            self::TYPE_EDUCATOR => 'Zaposleni u prosveti',
            self::TYPE_REPRESSED_PERSON => 'Građani pogođeni represijom',
        ];
    }

    public static function getHrType($type): string
    {
        return static::getHrTypes()[$type];
    }
}
