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
        return ['paymentMethods' => 'pm', 'registeredPeriods' => 'rp', 'school' => 's'];
    }


    public function getSearchableColumns(): array
    {
        return ['a.name', 'a.status', 'pm.accountNumber', 'pm.wireInstructions'];
    }

    public function getColumnsToCount(): array
    {
        return [];
    }

    /**
     * @param int|null $excludeStatus status to leave out, or null to count everyone who has
     *                                ever been registered — including those since removed,
     *                                which is what "people supported" means on the front page.
     */
    public function getBeneficiaryCount(?int $excludeStatus = Beneficiary::STATUS_DELETED): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('COUNT(b.id)')
            ->from(Beneficiary::class, 'b');

        if ($excludeStatus !== null) {
            $qb->where('b.status != :status')
                ->setParameter('status', $excludeStatus);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function fetchByPeriod(int $periodId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('b')
            ->addSelect('COALESCE(SUM(t.amount), 0) AS HIDDEN receivedAmount')
            // Share of this period's target already received. Beneficiaries who have
            // received the smallest fraction of what they need are served first; the
            // absolute amount breaks ties. NULLIF guards a zero/absent target.
            ->addSelect('(COALESCE(SUM(t.amount), 0) / NULLIF(rp.amount, 0)) AS HIDDEN receivedRatio')
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
            ->addGroupBy('rp.amount')
            ->orderBy('receivedRatio', 'ASC')
            ->addOrderBy('receivedAmount', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Release the beneficiaries a delegate holds *through a school*, ahead of the reclaim in
     * Delegate::update().
     *
     * The `school_id IS NOT NULL` guard is load-bearing. The reclaim that follows this call is
     * assignOrphanedBeneficiariesToDelegate(), which matches on school_id — so a beneficiary
     * with no school can be released here but never restored. MSPR has no schools and assigns
     * its delegate directly, which made every MSPR beneficiary collateral damage of an
     * unrelated edit to any of that delegate's MSP schools: createdBy cleared, silently, with
     * nothing able to put it back.
     *
     * School-held beneficiaries are untouched by the guard, so the existing MSP behaviour —
     * including the delegate-scoped over-release that Delegate::update() compensates for — is
     * unchanged.
     */
    public function nullifyCreatedByForDelegate(int $delegateId): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'UPDATE beneficiary SET createdBy_id = NULL WHERE createdBy_id = ? AND school_id IS NOT NULL',
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
