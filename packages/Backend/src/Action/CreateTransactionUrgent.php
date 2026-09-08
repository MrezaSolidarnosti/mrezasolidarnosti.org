<?php

namespace Solidarity\Backend\Action;

use Doctrine\DBAL\ArrayParameterType;
use Solidarity\Donor\Entity\Donor as DonorEntity;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Transaction\Entity\Transaction as TransactionEntity;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * A createTransactions round that goes to KNOWN-ACTIVE donors first.
 *
 *   php public/cli.php createTransactionsUrgent dry
 *   php public/cli.php createTransactionsUrgent run
 *   php public/cli.php createTransactionsUrgent run target=4000000
 *
 * WHY THIS EXISTS. The scheduled round orders donors by MAX(transaction.createdAt) ASC
 * (DonorRepository::getDonorsByProject) — least-recently-tried first. That is deliberate and
 * correct for its job: it walks the whole pledge base looking for who is still alive, and it
 * gives the longest-untried donor the next instruction. It is also, for the same reason, slow
 * to raise a specific sum, because most of what it tries is dormant by construction.
 *
 * When there is an urgent need, this one inverts that: most-recently-active donors first, so
 * the instructions land where somebody is most likely to act on them.
 *
 * IT IS A PATCH, and it should read like one. Switch it into deploy/crontab in place of
 * createTransactions for as long as the need lasts, then switch it back. Nothing schedules it
 * on its own and nothing depends on it.
 *
 * EVERYTHING ELSE IS THE PARENT'S. Only selectDonors() is overridden — the holiday guard, the
 * per-donor isolation, the instructions mail, the dry-run rollback and the report all run
 * exactly as they do in the normal round. If a future variant needs a different rule, it
 * subclasses CreateTransaction the same way rather than forking the loop.
 */
class CreateTransactionUrgent extends CreateTransaction
{
    /**
     * A pledge remainder below this is not worth selecting a donor for: createBalancedForDonor()
     * skips it, so counting it toward a target would promise money the round cannot move.
     */
    private const MIN_USABLE = TransactionService::MIN_TRANSACTION_DONATION_AMOUNT;

    protected function roundName(): string
    {
        return 'CREATE TRANSACTIONS (URGENT — ACTIVE DONORS FIRST)';
    }

    /**
     * Eligible donors, most recently active first, optionally cut off at a target sum.
     *
     * ORDERING: most recent of "last honoured a donation" and "registered", descending —
     * newest signal of life first. Honoured means status 2, 3 or 7. Being ISSUED an
     * instruction is not evidence of anything (it is what the dormant sweep does to everyone),
     * and 5/6 are the evidence against, so neither belongs in the key. A donor who has never
     * donated ranks on their registration date, which is exactly right: a signup from last
     * week is a better bet than a donor who last paid two years ago.
     *
     * ELIGIBILITY is unchanged from the scheduled round — isActive = 1, status IN (NEW,
     * VERIFIED), pledged to one of the running projects. In particular donors flagged
     * TRY_TO_CONTACT / IGNORING_PAYMENTS by FlagNonPayingDonors stay out. Urgency is not a
     * reason to go back to people who have already stopped paying; they are the least likely
     * to convert and the most likely to complain.
     *
     * TARGET (`target=<rsd>`, optional). Donors are taken in rank order until their combined
     * spendable pledge reaches the target, then selection stops.
     *
     * Read that as an UPPER BOUND, not a forecast. Spendable is what the donors can give;
     * what the round actually allocates is also capped by unmet beneficiary need, the 30,000
     * per-person limit and payment-type matching, so the money that moves is always less —
     * often a lot less. Aim high, or run check/donor-activity.sql first and use what it says.
     * With no target the whole eligible pool is processed, still in activity order, which is
     * the safer default for an urgent round: under-delivering silently is the worse failure.
     */
    protected function selectDonors(array $projects, array $params): array
    {
        $projectIds = array_map(static fn ($p) => $p->id, $projects);
        if (!$projectIds) {
            return [];
        }

        $target = $this->targetFrom($params);
        $ranked = $this->rankedDonorIds($projectIds);

        $ids = [];
        $running = 0;
        foreach ($ranked as $row) {
            $ids[] = (int) $row['donorId'];
            $running += (int) $row['spendableRsd'];
            if ($target !== null && $running >= $target) {
                break;
            }
        }

        // Said out loud on every run. The whole point of this action is that it processes a
        // different set than the scheduled one, so a report that did not state which set would
        // be unreadable next to yesterday's log from the normal round.
        $summary = sprintf(
            'URGENT selection: %d of %d eligible donor(s), %s RSD spendable%s.',
            count($ids),
            count($ranked),
            number_format($running, 0),
            $target !== null ? sprintf(' (target %s RSD)', number_format($target, 0)) : ' (no target — whole pool)'
        );
        if ($target !== null && $running < $target) {
            $summary .= ' WARNING: pool exhausted before reaching the target.';
        }
        $this->getLogger()->log(\Monolog\Level::Info, $summary);
        echo $summary . PHP_EOL;

        if (!$ids) {
            return [];
        }

        // One query, then reordered by hand: findBy() returns rows in whatever order the
        // database gives back, and here the order IS the feature.
        $byId = [];
        foreach ($this->em->getRepository(DonorEntity::class)->findBy(['id' => $ids]) as $donor) {
            $byId[$donor->id] = $donor;
        }

        $donors = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $donors[$id] = $byId[$id];
            }
        }

        return $donors;
    }

    /** `target=4000000` anywhere in the argv tail. Absent or unparseable means no target. */
    private function targetFrom(array $params): ?int
    {
        foreach ($params as $param) {
            if (is_string($param) && str_starts_with($param, 'target=')) {
                $value = (int) substr($param, strlen('target='));

                return $value > 0 ? $value : null;
            }
        }

        return null;
    }

    /**
     * Eligible donor ids in activity order, each with the pledge money the cron could still
     * move for them.
     *
     * spendableRsd reproduces createBalancedForDonor()'s own budget arithmetic, because a
     * target computed from anything looser would select too few donors and quietly miss:
     *   - only pledges the cron will touch at all (monthly = 1 OR allocateUntilSpent = 1)
     *   - pledge converted to RSD by payment type (bank transfer is RSD, the rest EUR)
     *   - minus what is already allocated for that donor + project + payment type, over the
     *     window the pledge is counted in — last 30 days for monthly, all time otherwise
     *   - remainders under the 500 floor dropped, since the allocator would skip them
     *
     * Native SQL, like the same computation in Statistics::getRemainingPledged(): the per-
     * pledge floor needs GREATEST() over a correlated aggregate, and the DQL alternative is a
     * query per payment method across the whole donor base. check/donor-activity.sql is this
     * query with the ranking exposed — run that to decide the target, then pass it here.
     *
     * @param int[] $projectIds
     * @return array<int, array{donorId: int|string, spendableRsd: int|string}>
     */
    private function rankedDonorIds(array $projectIds): array
    {
        $sql = 'SELECT d.id AS donorId, sp.spendableRsd
                  FROM `donor` d
                  JOIN (
                        SELECT r.donor_id, SUM(r.remainingRsd) AS spendableRsd
                          FROM (
                                SELECT p.donor_id,
                                       GREATEST(p.pledgedRsd
                                                - COALESCE(IF(p.monthly = 1, s.spent30, s.spentAll), 0), 0) AS remainingRsd
                                  FROM (
                                        SELECT pm.donor_id, pm.project_id, pm.type,
                                               MAX(pm.monthly) AS monthly,
                                               SUM(CASE WHEN pm.type = :bankType
                                                        THEN pm.amount
                                                        ELSE ROUND(pm.amount * :rate) END) AS pledgedRsd
                                          FROM `donorPaymentMethod` pm
                                         WHERE (pm.monthly = 1 OR pm.allocateUntilSpent = 1)
                                           AND pm.project_id IN (:pledgeProjectIds)
                                         GROUP BY pm.donor_id, pm.project_id, pm.type
                                       ) p
                                  LEFT JOIN (
                                        SELECT t.donorId, t.projectId, t.paymentType,
                                               SUM(t.amount) AS spentAll,
                                               SUM(IF(t.createdAt >= :since, t.amount, 0)) AS spent30
                                          FROM `transaction` t
                                         WHERE t.status IN (:allocatedStatuses)
                                         GROUP BY t.donorId, t.projectId, t.paymentType
                                       ) s
                                    ON s.donorId = p.donor_id
                                   AND s.projectId = p.project_id
                                   AND s.paymentType = p.type
                               ) r
                         WHERE r.remainingRsd >= :minUsable
                         GROUP BY r.donor_id
                       ) sp ON sp.donor_id = d.id
             LEFT JOIN (
                        SELECT t.donorId, MAX(t.createdAt) AS lastDonationAt
                          FROM `transaction` t
                         WHERE t.status IN (:honouredStatuses)
                         GROUP BY t.donorId
                       ) hon ON hon.donorId = d.id
                 WHERE d.isActive = 1
                   AND d.status IN (:donorStatuses)
                   -- EXISTS rather than a join: a donor pledged to both projects would
                   -- otherwise come back twice and be counted twice toward the target.
                   AND EXISTS (
                        SELECT 1 FROM `donor_project` dp
                         WHERE dp.donor_id = d.id AND dp.project_id IN (:donorProjectIds)
                       )
                 ORDER BY GREATEST(COALESCE(hon.lastDonationAt, d.createdAt), d.createdAt) DESC,
                          d.id ASC';

        return $this->em->getConnection()->fetchAllAssociative(
            $sql,
            [
                'bankType' => DonorPaymentMethod::TYPE_BANK_TRANSFER,
                'rate' => TransactionEntity::EUR_TO_RSD_RATE,
                // Two names for one list: DBAL expands array placeholders positionally,
                // and a repeated named array parameter is not worth betting a cron job on.
                'pledgeProjectIds' => $projectIds,
                'donorProjectIds' => $projectIds,
                'since' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s'),
                'allocatedStatuses' => [
                    TransactionEntity::STATUS_NEW,
                    TransactionEntity::STATUS_WAITING_CONFIRMATION,
                    TransactionEntity::STATUS_CONFIRMED,
                    TransactionEntity::STATUS_PAID,
                ],
                'honouredStatuses' => [
                    TransactionEntity::STATUS_WAITING_CONFIRMATION,
                    TransactionEntity::STATUS_CONFIRMED,
                    TransactionEntity::STATUS_PAID,
                ],
                'donorStatuses' => [DonorEntity::STATUS_NEW, DonorEntity::STATUS_VERIFIED],
                'minUsable' => self::MIN_USABLE,
            ],
            [
                'pledgeProjectIds' => ArrayParameterType::INTEGER,
                'donorProjectIds' => ArrayParameterType::INTEGER,
                'allocatedStatuses' => ArrayParameterType::INTEGER,
                'honouredStatuses' => ArrayParameterType::INTEGER,
                'donorStatuses' => ArrayParameterType::INTEGER,
            ]
        );
    }
}
