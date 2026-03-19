<?php

namespace Solidarity\Beneficiary\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Transaction\Entity\Project;

#[ORM\Entity]
#[ORM\Table(name: 'beneficiaryPaymentMethod')]
class PaymentMethod
{
    use Timestampable;

    const TYPE_BANK_TRANSFER = 1;
    const TYPE_WIRE_TRANSFER = 2;
    const TYPE_WESTERN_UNION = 3;
    const TYPE_MONEYGRAM = 4;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $type;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $accountNumber;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    public ?string $wireInstructions;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    public Beneficiary $beneficiary;

//    #[ORM\ManyToOne(targetEntity: Project::class)]
//    #[ORM\JoinColumn(nullable: false)]
//    public Project $project;

    public static function getHrTypes(): array
    {
        return array(
            self::TYPE_BANK_TRANSFER => 'Bankovni transfer (lokalni)',
            self::TYPE_WIRE_TRANSFER => 'Bankovni transfer (međunarodni)',
            self::TYPE_WESTERN_UNION => 'Western Union',
            self::TYPE_MONEYGRAM => 'Moneygram',
        );
    }

    public static function getHrType($type): string
    {
        return static::getHrTypes()[$type];
    }
}