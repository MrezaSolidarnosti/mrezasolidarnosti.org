<?php

namespace Solidarity\Transaction\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;

#[ORM\Entity]
#[ORM\Table(name: 'transaction')]
class Transaction
{
    use Timestampable;

    public const STATUS_NEW = 1;
    public const STATUS_WAITING_CONFIRMATION = 2;
    public const STATUS_CONFIRMED = 3;
    public const STATUS_CANCELLED = 4;
    public const STATUS_NOT_PAID = 5;
    public const STATUS_EXPIRED = 6;
    public const STATUS_PAID = 7;

    const PER_PERSON_LIMIT = 30000;
    const EUR_TO_RSD_RATE = 117.5;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $paymentType;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $accountNumber;
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    public ?string $instructions;
    #[ORM\Column(type: Types::INTEGER)]
    public int $amount;
    #[ORM\Column(type: Types::INTEGER)]
    public int $amountEur;
    #[ORM\Column(type: Types::INTEGER)]
    public int $status;
    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $donorConfirmed;
    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    public ?string $comment;
    // payment code provided by the payment institution, entered by the donor when confirming payment
    #[ORM\Column(type: Types::STRING, length: 256, nullable: true)]
    public ?string $paymentCode;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'projectId', referencedColumnName: 'id', unique: false, nullable: false)]
    public Project $project;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'periodId', referencedColumnName: 'id', unique: false, nullable: false)]
    public Period $period;

    #[ORM\ManyToOne(targetEntity: Donor::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'donorId', referencedColumnName: 'id', unique: false)]
    public Donor $donor;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'beneficiaryId', referencedColumnName: 'id', unique: false)]
    public Beneficiary $beneficiary;

    /**
     * Convert EUR amount to RSD.
     */
    public static function eurToRsd(int $eurAmount): int
    {
        return (int) round($eurAmount * self::EUR_TO_RSD_RATE);
    }

    /**
     * Convert RSD amount to EUR.
     */
    public static function rsdToEur(int $rsdAmount): float
    {
        return round($rsdAmount / self::EUR_TO_RSD_RATE, 2);
    }

    /**
     * Returns amount in original currency for display (EUR for non-RSD payment types).
     */
    public function getDisplayAmount(): float
    {
        if ($this->paymentType === 1) {
            return $this->amount;
        }
        return self::rsdToEur($this->amount);
    }

    /**
     * Returns currency label for display.
     */
    public function getDisplayCurrency(): string
    {
        return $this->paymentType === 1 ? 'RSD' : 'EUR';
    }

    public function getReferenceCode(): string
    {
        return 'MS'.$this->getId();
    }

    public static function getHrStatus($status): string
    {
        return self::getHrStatuses()[$status];
    }

    public static function getHrStatuses(): array
    {
        return array(
            self::STATUS_NEW => 'Čeka se uplata',
            self::STATUS_WAITING_CONFIRMATION => 'Čeka se potvrda građana',
            self::STATUS_CONFIRMED => 'Potvrđeno',
            self::STATUS_CANCELLED => 'Otkazano',
            self::STATUS_NOT_PAID => 'Nije plaćeno',
            self::STATUS_EXPIRED => 'Istekla',
            self::STATUS_PAID => 'plaćeno', // proveriti sta je ovaj status
        );
    }
}