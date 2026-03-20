<?php

namespace Solidarity\Beneficiary\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Factory\BeneficiaryFactory;
use Solidarity\Transaction\Entity\Transaction;
use Skeletor\Core\TableView\Repository\TableViewRepository;

class BeneficiaryRepository extends TableViewRepository
{
    const ENTITY = Beneficiary::class;
    const FACTORY = BeneficiaryFactory::class;

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    public function getJoinableEntities()
    {
        return ['paymentMethods' => 'pm', 'registeredPeriods' => 'rp'];
    }


    public function getSearchableColumns(): array
    {
        return ['a.name', 'a.status', 'pm.accountNumber', 'pm.wireInstructions'];
    }

    public function getColumnsToCount(): array
    {
        return [];
    }

    public function fetchByPeriod(int $periodId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('b')
            ->addSelect('COALESCE(SUM(t.amount), 0) AS HIDDEN receivedAmount')
            ->from(static::ENTITY, 'b')
            ->join('b.registeredPeriods', 'rp')
            ->leftJoin('b.transactions', 't', 'WITH', 't.status IN (:transactionStatuses) AND t.period = :periodId')
            ->where('rp.period = :periodId')
            ->andWhere('b.status = :status')
            ->setParameter('periodId', $periodId)
            ->setParameter('status', Beneficiary::STATUS_NEW)
            ->setParameter('transactionStatuses', [
                Transaction::STATUS_CONFIRMED,
                Transaction::STATUS_PAID,
            ])
            ->groupBy('b.id')
            ->orderBy('receivedAmount', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function nullifyCreatedByForDelegate(int $delegateId): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'UPDATE beneficiary SET createdBy_id = NULL WHERE createdBy_id = ?',
            [$delegateId]
        );
    }

    public function assignOrphanedBeneficiariesToDelegate(int $schoolId, int $delegateId): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'UPDATE beneficiary SET createdBy_id = ? WHERE school_id = ? AND createdBy_id IS NULL',
            [$delegateId, $schoolId]
        );
    }
}
