<?php

namespace Solidarity\Educator\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\User\Entity\User;
use Solidarity\School\Entity\School;
use Solidarity\Transaction\Entity\Transaction;

#[ORM\Entity]
//#[ORM\Index(name: 'idx_period', columns: ['period_id', 'school_id', 'accountNumber'])]
#[ORM\Index(name: 'idx_period', columns: ['period_id', 'accountNumber'])]
// @TODO check
#[ORM\UniqueConstraint(name: 'idx_period_account_unq', columns: ['period_id', 'accountNumber'])]
//#[ORM\Index(name: 'idx_create_transaction', columns: ['period_id', 'status'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Table(name: 'educator')]
class Educator
{
    use Timestampable;

//    const STATUS_NEW = 1;
//    const STATUS_FOR_SENDING = 2;
//    const STATUS_SENT = 3;
//    const STATUS_GAVE_UP = 4;
//    const STATUS_RECEIVED = 5;
//    const STATUS_DUPLICATE = 6;
//    const STATUS_PROBLEM = 7;

    public const MONTHLY_LIMIT = 120000;

    public const STATUS_NEW = 1;
    public const STATUS_DELETED = 2;
    public const STATUS_GAVE_UP = 4;
    public const STATUS_PROBLEM = 7;

    #[ORM\Column(type: Types::INTEGER)]
    public int $amount;
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;
    #[ORM\Column(type: Types::INTEGER)]
    public int $status;
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $accountNumber;
    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    public ?string $comment;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $statusComment = null;
    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'educators')]
    #[ORM\JoinColumn(name: 'school_id', referencedColumnName: 'id', unique: false, nullable: true)]
    public ?School $school;

    #[ORM\ManyToOne(inversedBy: 'damagedEducators')]
    #[ORM\JoinColumn]
    public ?Delegate $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'damagedEducators')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Period $period = null;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'transactions')]
    public Collection $transactions;

//    #[ORM\Column(type: 'datetime', insertable: true, updatable: true, options: ['default' => "CURRENT_TIMESTAMP"])]
//    public \DateTime $createdAt;

    public static function getHrStatuses(): array
    {
        return array(
            self::STATUS_NEW => 'Ok',
            self::STATUS_GAVE_UP => 'Gave up',
            self::STATUS_PROBLEM => 'Problem',
            self::STATUS_DELETED => 'Deleted',
        );
    }

    public static function getHrStatus($status): string
    {
        return static::getHrStatuses()[$status];
    }
}