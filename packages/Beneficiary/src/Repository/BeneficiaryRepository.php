<?php

namespace Solidarity\Beneficiary\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Factory\BeneficiaryFactory;
use Solidarity\School\Entity\School;
use Solidarity\Transaction\Entity\Transaction;
use Skeletor\Core\TableView\Repository\TableViewRepository;

class BeneficiaryRepository extends TableViewRepository
{
    const ENTITY = Beneficiary::class;
    const FACTORY = BeneficiaryFactory::class;

    /** Uncountable-filter key: limit the table to what this delegate (id) may see. */
    const DELEGATE_SCOPE = 'delegateScope';

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    public function getJoinableEntities()
    {
        return ['paymentMethods' => 'pm', 'registeredPeriods' => 'rp', 'school' => 's'];
    }


    /**
     * The one definition of "beneficiaries this delegate works with":
     *  - any beneficiary of a school the delegate is assigned to - shared with every other
     *    delegate of that school, they are all at the same level, or
     *  - a school-less (MSPR) beneficiary assigned to the delegate directly via createdBy.
     *
     * For a school beneficiary createdBy is deliberately ignored: it is only the contact on
     * record, and a delegate who leaves the school loses access even if their id is still there.
     *
     * Used for the beneficiary list and, through the `b` join, the transaction list and
     * TransactionRepository::belongsToDelegate(). Beneficiary::isVisibleToDelegate() is the
     * same rule in PHP, for a single loaded entity - change both together.
     */
    public static function addDelegateScope(QueryBuilder $qb, string $beneficiaryAlias, int $delegateId): void
    {
        $qb->andWhere(sprintf(
            '((IDENTITY(%1$s.school) IN (SELECT scope_s.id FROM %2$s scope_s JOIN scope_s.delegates scope_d WHERE scope_d.id = :delegateScope))'
            . ' OR (%1$s.school IS NULL AND %1$s.createdBy = :delegateScope))',
            $beneficiaryAlias,
            School::class
        ));
        $qb->setParameter('delegateScope', $delegateId);
    }

    /**
     * Two keys mean "by delegate" here and both go through addDelegateScope(), not the
     * generic `a.createdBy = x`, which would miss every school beneficiary whose contact is a
     * co-delegate:
     *  - DELEGATE_SCOPE (uncountable) - set by the service for a logged-in delegate;
     *  - createdBy (column filter) - the staff "Delegat" column filter.
     * They never meet: the column is only offered to staff.
     */
    protected function applyCustomFilters(QueryBuilder $qb, array &$filter, array &$uncountableFilter): void
    {
        if (isset($uncountableFilter[self::DELEGATE_SCOPE])) {
            static::addDelegateScope($qb, 'a', (int) $uncountableFilter[self::DELEGATE_SCOPE]);
            unset($uncountableFilter[self::DELEGATE_SCOPE]);
        } elseif (isset($filter['createdBy']) && is_scalar($filter['createdBy']) && $filter['createdBy'] !== '') {
            static::addDelegateScope($qb, 'a', (int) trim((string) $filter['createdBy'], '"'));
        }
        unset($filter['createdBy']);
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
}
