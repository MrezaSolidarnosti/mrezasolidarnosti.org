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
        }

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
     * Returns remaining amount under the per-person limit (across all projects).
     * Returns 0 if limit is already reached.
     */
    public function getRemainingPerPersonLimit(Donor $donor, Beneficiary $beneficiary): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(t.amount)')
            ->from(static::ENTITY, 't')
            ->where('t.donor = :donor')
            ->andWhere('t.beneficiary = :beneficiary')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('donor', $donor->getId())
            ->setParameter('beneficiary', $beneficiary->getId())
            ->setParameter('statuses', $this->getAllocatedStatuses());

        $donated = (int) $qb->getQuery()->getSingleScalarResult();
        return max(0, Transaction::PER_PERSON_LIMIT - $donated);
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