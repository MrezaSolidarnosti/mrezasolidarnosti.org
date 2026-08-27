<?php
namespace Solidarity\Transaction\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Factory\TransactionFactory;
use Skeletor\Core\TableView\Repository\TableViewRepository;

class TransactionRepository extends TableViewRepository
{
    const ENTITY = Transaction::class;
    const FACTORY = TransactionFactory::class;

    // tmp solution
    const PROJECT_MSP = 1;
    const PROJECT_MSPR = 2;

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    /**
     * Statuses that count as "allocated" money (not cancelled/expired).
     */
    private function getAllocatedStatuses(): array
    {
        return [
            Transaction::STATUS_NEW,
            Transaction::STATUS_WAITING_CONFIRMATION,
            Transaction::STATUS_CONFIRMED,
            Transaction::STATUS_PAID,
        ];
    }

    public function getPaidSumAmountForDonorPerProject(Donor $donor, Project $project, ?int $paymentType = null): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(t.amount)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->andWhere('t.project = :project')
            ->andWhere('t.status IN (:includedStatuses)')
            ->setParameter('donor', $donor->getId())
            ->setParameter('project', $project->getId())
            ->setParameter('includedStatuses', $this->getAllocatedStatuses());

        if ($paymentType !== null) {
            $qb->andWhere('t.paymentType = :paymentType')
                ->setParameter('paymentType', $paymentType);

            // If donor has monthly enabled for this payment method, only count last 30 days
            foreach ($donor->getPaymentMethodsForProject($project) as $donorPM) {
                if ($donorPM->type === $paymentType && $donorPM->monthly) {
                    $qb->andWhere('t.createdAt >= :since')
                        ->setParameter('since', new \DateTimeImmutable('-30 days'));
                    break;
                }
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Total allocated amount for a donor across all projects and all payment types.
     */
    public function getPaidSumAmountForDonor(Donor $donor): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(t.amount)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->andWhere('t.status IN (:includedStatuses)')
            ->setParameter('donor', $donor->getId())
            ->setParameter('includedStatuses', $this->getAllocatedStatuses());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Number of allocated transactions for a donor across all projects and all payment types.
     */
    public function getTransactionCountForDonor(Donor $donor): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->andWhere('t.status IN (:includedStatuses)')
            ->setParameter('donor', $donor->getId())
            ->setParameter('includedStatuses', $this->getAllocatedStatuses());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Payment instructions (transactions) for a donor, newest first, paginated.
     * The beneficiary is eager-joined so the caller can read its name without extra
     * queries. The project is intentionally NOT fetch-joined: Transaction.project
     * declares inversedBy 'transactions' but Project has no such inverse collection,
     * so selecting it would crash the hydrator. It lazy-loads fine for getReferenceCode().
     *
     * @return Transaction[]
     */
    public function getInstructionsForDonor(Donor $donor, int $offset, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t', 'b')
            ->from(static::ENTITY, 't')
            ->leftJoin('t.beneficiary', 'b')
            ->where('t.donor = :donor')
            ->setParameter('donor', $donor->getId())
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function getInstructionsCountForDonor(Donor $donor): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->setParameter('donor', $donor->getId());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getSumAmountForBeneficiary(Beneficiary $beneficiary, ?Project $project = null, ?Period $period = null): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(t.amount)')
            ->from(static::ENTITY, 't')
            ->where('t.beneficiary = :beneficiary')
            ->setParameter('beneficiary', $beneficiary->getId())
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('statuses', $this->getAllocatedStatuses());
        if ($project) {
            $qb->andWhere('t.project = :project')
                ->setParameter('project', $project->getId());
        }
        if ($period) {
            $qb->andWhere('t.period = :period')
                ->setParameter('period', $period->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statuses that count as "realized" money actually delivered through the network.
     */
    private function getRealizedStatuses(): array
    {
        return [
            Transaction::STATUS_CONFIRMED,
            Transaction::STATUS_PAID,
        ];
    }

    /**
     * Total amount (RSD) realized through the network across all confirmed/paid transactions.
     */
    public function getTotalNetworkedAmount(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COALESCE(SUM(t.amount), 0)')
            ->from(static::ENTITY, 't')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', $this->getRealizedStatuses());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTransactionsBySchool($schoolId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(static::ENTITY, 't')
            ->join(Beneficiary::class, 'b', 'WITH', 't.beneficiary = b')
            ->where('b.school = :school')
            ->andWhere('t.project = :project');
        $qb->setParameter('school', $schoolId);
        $qb->setParameter('project', static::PROJECT_MSP);

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns the remaining amount under the per-person limit for the current
     * calendar year (across all projects). The cap (Transaction::PER_PERSON_LIMIT)
     * is a yearly limit, so only transactions created from January 1st of the
     * current year onward are counted. Returns 0 if the limit is already reached.
     */
    public function getRemainingPerPersonLimit(Donor $donor, Beneficiary $beneficiary): int
    {
        $yearStart = (new \DateTimeImmutable('first day of January this year'))->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(t.amount)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->andWhere('t.beneficiary = :beneficiary')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.createdAt >= :yearStart')
            ->setParameter('donor', $donor->getId())
            ->setParameter('beneficiary', $beneficiary->getId())
            ->setParameter('statuses', $this->getAllocatedStatuses())
            ->setParameter('yearStart', $yearStart);

        $donated = (int) $qb->getQuery()->getSingleScalarResult();
        return max(0, Transaction::PER_PERSON_LIMIT - $donated);
    }

    /**
     * Does this transaction belong to a beneficiary the given delegate owns?
     *
     * Scoping the table alone is not access control — it only decides what is listed. Every
     * write endpoint takes an id straight off the URL or the request body, so without this the
     * rows a delegate cannot see are still ones they can change.
     */
    public function belongsToDelegate(int $transactionId, int $delegateId): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(static::ENTITY, 't')
            ->join('t.beneficiary', 'b')
            ->where('t.id = :transactionId')
            ->andWhere('b.createdBy = :delegateId')
            ->setParameter('transactionId', $transactionId)
            ->setParameter('delegateId', $delegateId);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function getJoinableEntities(): array
    {
        return [
            'donor' => 'd',
            'beneficiary' => 'b',
        ];
    }

    public function getSearchableColumns(): array
    {
        return ['d.email', 'd.firstName', 'd.lastName', 'a.accountNumber', 'a.instructions', 'b.name', 'a.accountNumber'];
    }

    public function getColumnsToCount(): array
    {
        return ['amount'];
    }

}