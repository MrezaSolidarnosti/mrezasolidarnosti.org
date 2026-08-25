<?php

namespace Solidarity\Backend\Blocks\HeroStats;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Skeletor\Core\Config\Config;
use Solidarity\Beneficiary\Service\Beneficiary;
use Solidarity\Donor\Entity\Donor as DonorEntity;
use Solidarity\Donor\Service\Donor;
use Solidarity\Transaction\Entity\Transaction as TransactionEntity;
use Solidarity\Transaction\Service\Transaction;

class HeroStatsViewFilter implements BlockViewFilterInterface
{
    public function __construct(
        protected Donor $donorService,
        protected Beneficiary $beneficiaryService,
        protected Transaction $transactionService,
        protected ?Config $config = null
    )
    {

    }

    public function filter(array $data): array
    {
        $donorCount = $this->donorService->getDonorCount(DonorEntity::STATUS_VERIFIED, true);
        $data['donorCount'] = number_format($donorCount, 0, ',', '.');

        // The headline total carries the historical adjustment: confirmed donations the
        // legacy app destroyed when it cascade-deleted inactive donors, which cannot be
        // rebuilt from any surviving source. Applied here rather than in
        // getTotalNetworkedAmount() so it stays a presentation figure — every other caller of
        // that method, and the transaction table itself, keep only what they can evidence.
        //
        // No note is rendered alongside it: the caveat needs more context than a visitor to
        // the front page can be given, and it is explained in full on the admin statistics
        // page. See config `historicalAdjustment` and Statistics::historicalAdjustment().
        $totalAmount = $this->transactionService->getTotalNetworkedAmount() + $this->historicalAdjustment();
        $data['totalAmount'] = number_format($totalAmount, 0, ',', '.');
        $data['totalAmountEur'] = number_format(TransactionEntity::rsdToEur($totalAmount), 2, ',', '.');

        // null, not the default: "Podržanih" means everyone the network has ever supported,
        // so it must include people since marked deleted. The default excludes them, which
        // counts only those currently active — a much smaller number, and not what the label
        // claims. The admin statistics page already counts them in, for the same reason.
        $supportedCount = $this->beneficiaryService->getBeneficiaryCount(null);
        $data['supportedCount'] = number_format($supportedCount, 0, ',', '.');

        return $data;
    }

    /**
     * The front page shows one network-wide figure, so every project's adjustment applies.
     *
     * Config is optional so the block still constructs without it — a missing or unset
     * adjustment simply yields 0, which is the correct behaviour for any deployment that
     * never had this problem.
     */
    private function historicalAdjustment(): int
    {
        if (!$this->config || !$this->config->offsetExists('historicalAdjustment')) {
            return 0;
        }

        $total = 0;
        foreach ($this->config->offsetGet('historicalAdjustment') as $entry) {
            $total += (int) ($entry->amount ?? 0);
        }

        return $total;
    }
}
