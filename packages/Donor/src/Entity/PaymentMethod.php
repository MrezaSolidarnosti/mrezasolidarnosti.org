<?php

namespace Solidarity\Donor\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Solidarity\Transaction\Entity\Project;

#[ORM\Entity]
#[ORM\Table(name: 'donorPaymentMethod')]
class PaymentMethod
{
    use Timestampable;

    const TYPE_BANK_TRANSFER = 1;
    const TYPE_WIRE_TRANSFER = 2;
    const TYPE_WESTERN_UNION = 3;
    const TYPE_MONEYGRAM = 4;

    const CURRENCY_RSD = 1;
    const CURRENCY_EUR = 2;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $type;

    #[ORM\Column(type: Types::INTEGER)]
    public int $amount;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $monthly;

    /**
     * Let the cron allocate from this pledge until the pledged amount is used up.
     *
     * `monthly` is NOT this flag. It selects the counting window — a monthly pledge measures
     * spend over the last 30 days, so it replenishes and never runs out. This one measures
     * spend over all time, so it drains once and then stops on its own: when
     * pledged - allocated falls under MIN_TRANSACTION_DONATION_AMOUNT the pledge is inert
     * forever, with no expiry to set and nothing to clean up.
     *
     * It exists for the changeover. The legacy app let a donor pledge a one-time amount and
     * spread it over several instructions automatically; this app moved one-time giving to
     * something the donor initiates from their profile. The pledges migrated from the old app
     * were made under the old rules and are still owed, so they carry this flag while
     * everything created since defaults to 0 and stays out of the cron.
     *
     * Also the mechanism if lump-sum pledges are ever offered again deliberately.
     */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    public int $allocateUntilSpent = 0;

    #[ORM\ManyToOne(targetEntity: Donor::class, inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    public Donor $donor;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Project $project;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $currency;

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

    public static function getCurrencies(): array
    {
        return [
            self::CURRENCY_RSD => 'RSD',
            self::CURRENCY_EUR => 'EUR',
        ];
    }

    public static function getCurrency(int $currency): string
    {
        return static::getCurrencies()[$currency];
    }
}