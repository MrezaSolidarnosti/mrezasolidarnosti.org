<?php

namespace Solidarity\Backend\Action;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Transaction\Entity\Transaction;

/**
 * Take donors whose payment instructions keep going unpaid out of allocation.
 *
 * An unpaid instruction is not free. NEW counts as allocated
 * (TransactionRepository::getAllocatedStatuses), so for its whole 72h life it consumes the
 * donor's pledged budget AND the beneficiary's remaining need for the period. A donor who
 * never pays therefore does not merely waste their own slot — they hold a beneficiary's need
 * hostage, once per round, keeping real money out.
 *
 * Two flags, because ExpireInstructions already tells the two failure modes apart and they
 * call for opposite remedies:
 *
 *   - STATUS_EXPIRED   (logged in, never confirmed)  -> the donor is receiving the mail and
 *                       ignoring it. STATUS_IGNORING_PAYMENTS: a shadow ban.
 *   - STATUS_NOT_PAID  (never came back at all)      -> the mail is not reaching a human.
 *                       STATUS_TRY_TO_CONTACT: chase them by some other route.
 *
 * Each kind is counted against the threshold SEPARATELY, never summed. Summing them would
 * shadow-ban a donor who never received a single email — wrong, and unfixable from their
 * side. It also leaves deliberate room for a patchy donor: two ignored plus two unseen is
 * four misses in a row and still no flag.
 *
 * It also runs the flags DOWN again, so nobody has to track them by hand: a TRY_TO_CONTACT
 * donor is let back in once the cooldown lapses, or as soon as they visit the site. Not
 * IGNORING_PAYMENTS — those clear their own flag by paying (CreateInstruction does not gate on
 * donor status), and a timer would only hand them another beneficiary's need to sit on.
 *
 * ORDER MATTERS: run this between expireInstructions and createTransactions. After expiry, so
 * this round's misses are already counted; before allocation, so a donor who crosses the line
 * this round is excluded from this round rather than being handed one more instruction first.
 *
 * Run: php public/cli.php flagDonors run
 * Preview without writing: php public/cli.php flagDonors dry
 */
class FlagNonPayingDonors extends Html
{
    /**
     * Misses of ONE kind, in a row, before the donor is flagged.
     *
     * Counted as a streak, not a lifetime total: a donor who has paid forty times and missed
     * the last three needs a pause, one who missed three spread over a year does not. Any
     * honoured instruction ends the streak, and so does a human changing their status.
     */
    private const FLAG_AFTER_MISSES = 3;

    /**
     * Days a TRY_TO_CONTACT donor waits before being let back into allocation on their own.
     *
     * The flag is a timeout, not a verdict. That donor never came back to the site, so nothing
     * they do will clear it and nobody has to remember they exist — without an expiry they sit
     * there until a human happens to notice, which for a small team means forever.
     *
     * Deliberately NOT applied to IGNORING_PAYMENTS. Those donors already have a way out under
     * their own steam: CreateInstruction does not gate on donor status, so they can make a
     * one-time donation, pay it, and ConfirmPayment clears them. Releasing them on a timer
     * would just hand them another beneficiary's need to sit on.
     */
    private const RELEASE_CONTACT_AFTER_DAYS = 60;

    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        private EntityManagerInterface $em,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        // CliSkeletor passes the argv tail as the "params" attribute.
        $params = (array) $request->getAttribute('params', []);
        $dry = in_array('dry', $params, true);

        echo sprintf('=== FLAG DONORS %s — %s ===', $dry ? 'DRY-RUN' : 'RUN', date('Y-m-d H:i:s')) . PHP_EOL;

        // Released before flagging, so a donor let back in starts from a clean slate: the
        // release stamps statusChangedAt, which is where the streak count begins, so their old
        // misses cannot re-flag them in this same run.
        $released = $this->releaseContactableDonors($dry);

        $candidates = $this->candidates();
        if (!$candidates) {
            echo sprintf('No donor is over the threshold. %d released.', $released) . PHP_EOL;

            return $response;
        }

        /** @var array<int, int[]> targetStatus => donor ids */
        $toFlag = [];

        echo sprintf("\n%-8s %-38s %10s %10s  %s\n", 'ID', 'Email', 'not paid', 'ignored', 'Becomes');
        foreach ($candidates as $row) {
            $donorId = (int) $row['donorId'];
            $notPaid = (int) $row['notPaidCount'];
            $expired = (int) $row['expiredCount'];

            // Each kind against the threshold on its own. Ignoring is checked first, so a
            // donor over the line on both is shadow-banned rather than merely contacted:
            // once they have ignored three, they are demonstrably receiving the mail.
            if ($expired >= self::FLAG_AFTER_MISSES) {
                $status = Donor::STATUS_IGNORING_PAYMENTS;
            } elseif ($notPaid >= self::FLAG_AFTER_MISSES) {
                $status = Donor::STATUS_TRY_TO_CONTACT;
            } else {
                continue; // the SQL should not return these, but the rule lives here
            }

            echo sprintf(
                "%-8d %-38s %10d %10d  %s\n",
                $donorId,
                mb_substr((string) $row['email'], 0, 38),
                $notPaid,
                $expired,
                Donor::getHrStatus($status)
            );
            $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                'Donor %d (%s): %d not paid, %d ignored in a row -> %s',
                $donorId,
                $row['email'],
                $notPaid,
                $expired,
                Donor::getHrStatus($status)
            ));

            $toFlag[$status][] = $donorId;
        }

        $flagged = array_sum(array_map('count', $toFlag));
        if (!$dry) {
            foreach ($toFlag as $status => $ids) {
                $this->apply($status, $ids);
            }
        }

        $summary = sprintf(
            'flagDonors %s finished: %d donor(s) flagged (%d ignoring, %d for contact), %d released.',
            $dry ? 'DRY-RUN' : 'RUN',
            $flagged,
            count($toFlag[Donor::STATUS_IGNORING_PAYMENTS] ?? []),
            count($toFlag[Donor::STATUS_TRY_TO_CONTACT] ?? []),
            $released
        );
        $this->getLogger()->log(\Monolog\Level::Info, $summary);
        echo PHP_EOL . $summary . PHP_EOL;
        if ($dry) {
            echo '=== DRY-RUN — nothing was written. ===' . PHP_EOL;
        }

        return $response;
    }

    /**
     * Let TRY_TO_CONTACT donors back into allocation, on either of two signals.
     *
     *   - the cooldown has run out. Nobody has to track them; the flag simply lapses.
     *   - lastVisit has moved since the flag was set. They came back to the site, which is
     *     precisely the thing this flag says they were not doing, so there is no reason to
     *     keep waiting out the clock.
     *
     * That second test only means anything for THIS flag. IGNORING_PAYMENTS is derived from
     * STATUS_EXPIRED, which is defined as "logged in but never confirmed" — those donors visit
     * by definition, so a visit-based release would clear every one of them the moment it ran.
     * Neither signal is applied to them: they clear their own flag by paying, and a timer would
     * only hand them another beneficiary's need to sit on.
     *
     * statusChangedAt IS NOT NULL is required, not incidental: a donor flagged by hand in the
     * admin has whatever timestamp they had before (possibly none), and releasing on an unknown
     * age would undo a human's decision days after they made it.
     *
     * @return int donors released, or that would have been
     */
    private function releaseContactableDonors(bool $dry): int
    {
        $cutoff = new \DateTimeImmutable('-' . self::RELEASE_CONTACT_AFTER_DAYS . ' days');

        $rows = $this->em->getConnection()->executeQuery(
            'SELECT d.id AS donorId, d.email AS email, d.statusChangedAt AS flaggedAt, d.lastVisit AS lastVisit
               FROM `donor` d
              WHERE d.status = :flag
                AND d.statusChangedAt IS NOT NULL
                AND (d.statusChangedAt < :cutoff
                     OR (d.lastVisit IS NOT NULL AND d.lastVisit > d.statusChangedAt))',
            [
                'flag' => Donor::STATUS_TRY_TO_CONTACT,
                'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            ]
        )->fetchAllAssociative();

        if (!$rows) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $reason = ($row['lastVisit'] !== null && $row['lastVisit'] > $row['flaggedAt'])
                ? 'visited the site since being flagged'
                : sprintf('%d day cooldown elapsed', self::RELEASE_CONTACT_AFTER_DAYS);

            echo sprintf("  release  %-8d %-38s  %s\n", (int) $row['donorId'], mb_substr((string) $row['email'], 0, 38), $reason);
            $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                'Donor %d (%s) released to VERIFIED: %s',
                $row['donorId'],
                $row['email'],
                $reason
            ));
            $ids[] = (int) $row['donorId'];
        }

        if (!$dry) {
            // statusChangedAt moves with the status, as everywhere else: it restarts the streak
            // count, so a released donor is judged on what they do next rather than on the
            // history that got them flagged.
            $this->em->getConnection()->executeStatement(
                'UPDATE `donor` SET status = :status, statusChangedAt = NOW() WHERE id IN (:ids)',
                ['status' => Donor::STATUS_VERIFIED, 'ids' => $ids],
                ['ids' => ArrayParameterType::INTEGER]
            );
        }

        return count($ids);
    }

    /**
     * Every eligible donor with an unbroken run of unpaid instructions, and how it breaks down.
     *
     * Scans the whole donor table rather than only those touched by the run that just expired
     * something. That makes it idempotent and self-healing: a donor who crossed the threshold
     * during an earlier run — before this existed, or in a run that died halfway — is picked
     * up on the next pass instead of being missed forever.
     *
     * @return array<int, array<string, mixed>>
     */
    private function candidates(): array
    {
        return $this->em->getConnection()->executeQuery(
            'SELECT d.id AS donorId,
                    d.email AS email,
                    SUM(t.status = :notPaidStatus) AS notPaidCount,
                    SUM(t.status = :expiredStatus) AS expiredCount
               FROM `donor` d
               JOIN `transaction` t
                 ON t.donorId = d.id
                AND t.status IN (:missStatuses)
                -- A human clearing the status restarts the count; without this the admin
                -- fixes a donor and the next run immediately re-flags them on old history.
                AND (d.statusChangedAt IS NULL OR t.createdAt > d.statusChangedAt)
                -- "In a row": no honoured instruction after this miss. Any payment ends the
                -- streak, so there is no counter to keep anywhere.
                AND NOT EXISTS (
                      SELECT 1 FROM `transaction` h
                       WHERE h.donorId = d.id
                         AND h.status IN (:honouredStatuses)
                         AND h.createdAt > t.createdAt
                    )
              -- Only donors a human has not already ruled on. An existing flag is theirs to
              -- clear, or the donor to clear by paying. PROBLEM/DELETED are never touched.
              WHERE d.status IN (:openStatuses)
              GROUP BY d.id, d.email
             HAVING expiredCount >= :threshold OR notPaidCount >= :threshold
              ORDER BY expiredCount DESC, notPaidCount DESC',
            [
                'notPaidStatus' => Transaction::STATUS_NOT_PAID,
                'expiredStatus' => Transaction::STATUS_EXPIRED,
                'missStatuses' => [Transaction::STATUS_NOT_PAID, Transaction::STATUS_EXPIRED],
                'honouredStatuses' => [
                    Transaction::STATUS_WAITING_CONFIRMATION,
                    Transaction::STATUS_CONFIRMED,
                    Transaction::STATUS_PAID,
                ],
                'openStatuses' => [Donor::STATUS_NEW, Donor::STATUS_VERIFIED],
                'threshold' => self::FLAG_AFTER_MISSES,
            ],
            [
                'missStatuses' => ArrayParameterType::INTEGER,
                'honouredStatuses' => ArrayParameterType::INTEGER,
                'openStatuses' => ArrayParameterType::INTEGER,
            ]
        )->fetchAllAssociative();
    }

    /**
     * statusChangedAt moves with the status: it is the point the streak count restarts from,
     * so a donor flagged now cannot be re-flagged on the same history once a human clears them.
     *
     * @param int[] $donorIds
     */
    private function apply(int $status, array $donorIds): void
    {
        if (!$donorIds) {
            return;
        }

        $this->em->getConnection()->executeStatement(
            'UPDATE `donor` SET status = :status, statusChangedAt = NOW() WHERE id IN (:ids)',
            ['status' => $status, 'ids' => $donorIds],
            ['ids' => ArrayParameterType::INTEGER]
        );
    }
}
