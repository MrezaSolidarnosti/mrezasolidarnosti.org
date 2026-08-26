<?php
namespace Solidarity\Donor\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Login\Repository\LoginRepositoryInterface;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Donor\Factory\DonorFactory;
use Skeletor\Core\TableView\Repository\TableViewRepository;
use Solidarity\Transaction\Entity\Project;

class DonorRepository extends TableViewRepository implements LoginRepositoryInterface
{
    const ENTITY = Donor::class;
    const FACTORY = DonorFactory::class;

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    public function findByEmail(string $email)
    {
        return $this->entityManager->getRepository(Donor::class)->findOneBy(['email' => $email]);
    }

    public function updatePassword($userId, $password) { /* no-op, passwordless */ }

    public function updateLoginInfo($model)
    {
        $this->entityManager->persist($model);
        $this->entityManager->flush();
    }

    /**
     * Stamp the donor's last visit. Runs on every authenticated frontend request, so it is
     * a single indexed UPDATE rather than a load-compare-flush cycle, and the throttle is
     * part of the WHERE clause: rows already stamped within the window are simply not
     * matched, costing one cheap write per donor per window instead of one per page view.
     */
    public function touchLastVisit(int $donorId, int $throttleSeconds = 300): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(Donor::class, 'd')
            ->set('d.lastVisit', ':now')
            ->where('d.id = :id')
            // Parentheses are mandatory: Doctrine does not add them around a raw string,
            // and AND binds tighter than OR — without them this reads as
            // "(id = :id AND lastVisit IS NULL) OR lastVisit < :threshold"
            // and stamps every donor in the table on every request.
            ->andWhere('(d.lastVisit IS NULL OR d.lastVisit < :threshold)')
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $donorId)
            ->setParameter('threshold', new \DateTime('-' . $throttleSeconds . ' seconds'))
            ->getQuery()
            ->execute();
    }

    /**
     * Donors eligible for allocation, least recently contacted first.
     *
     * Ordered by when the donor was last given anything, not by id. With pledges exceeding
     * need — which is the normal state — the allocator drains the front of this list and never
     * reaches the back, so a fixed id order permanently serves the same people and permanently
     * starves everyone behind them. Ascending or descending makes no difference to that; it
     * only decides which end is starved.
     *
     * MAX(t.createdAt) is "when did we last ask this person", so:
     *   - donors who have never been allocated anything sort first (NULL leads on ASC in
     *     MySQL) — new registrations and the long-forgotten tail;
     *   - being allocated something moves a donor to the back on its own, so the rotation
     *     needs no cursor to persist and nothing to reset;
     *   - a donor who is processed but allocated nothing keeps their timestamp, and therefore
     *     keeps their place at the front for the next round, which is what should happen.
     *
     * Deliberately counts transactions across ALL projects, not just this one: it measures
     * contact, and the donor is mailed once per round regardless of which project funded it.
     * CreateTransaction merges the per-project lists and dedupes by id anyway, so the first
     * project a donor appears in fixes their position.
     *
     * Also deliberately every status, expired and cancelled included — an instruction the
     * donor ignored is still an approach we made, and re-asking them immediately is exactly
     * the pestering this ordering exists to avoid. Switch the join to the confirmed/paid
     * statuses only if the policy should become "keep asking whoever actually pays".
     *
     * @return Donor[]
     */
    public function getDonorsByProject($project): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('d')
            // HIDDEN so the result stays a list of Donor entities; same shape as
            // BeneficiaryRepository::fetchByPeriod(), which sorts on a hidden aggregate too.
            ->addSelect('MAX(t.createdAt) AS HIDDEN lastAllocatedAt')
            ->from(Donor::class, 'd')
            ->innerJoin('d.projects', 'p')
            ->leftJoin('d.transactions', 't')
            ->where('p.id = :projectId')
            ->andWhere('d.isActive = 1')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('projectId', $project->id)
            ->setParameter('statuses', [Donor::STATUS_VERIFIED, Donor::STATUS_NEW])
            ->groupBy('d.id')
            ->orderBy('lastAllocatedAt', 'ASC')
            // Stable tiebreak, so donors who have never been allocated anything (all NULL, so
            // all equal on the first key) still come out in a fixed, reproducible order —
            // without it the dry run could preview a different round than the one that commits.
            ->addOrderBy('d.id', 'ASC');
//            ->setMaxResults(100);

        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function getJoinableEntities(): array
    {
        return [
            'projects' => 'p',
            'paymentMethods' => 'pm',
            'transactions' => 't',
        ];
    }

    public function getSearchableColumns(): array
    {
        return ['a.email', 'a.status'];
    }

    public function getDonorCount(int $status, ?bool $isActive = true): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('COUNT(d.id)')
            ->from(Donor::class, 'd')
            ->where('d.status = :status')
            ->setParameter('status', $status);

        if ($isActive !== null) {
            $qb->andWhere('d.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function updateProfileData(int $donorId, string $firstName, string $lastName): void
    {
        $donor = $this->entityManager
            ->getRepository(Donor::class)
            ->find($donorId);

        $donor->firstName = $firstName;
        $donor->lastName = $lastName;

        $this->entityManager->flush();
    }

    public function updateDonationData(array $data): void
    {
        $donor = $this->entityManager->getRepository(Donor::class)->find($data['donorId']);
        if (!$donor) {
            return;
        }

        $projectIds = $data['project'] === -1 ? [1, 2] : [$data['project']];

        $projectRepository = $this->entityManager->getRepository(Project::class);
        $paymentMethodRepository = $this->entityManager->getRepository(PaymentMethod::class);

        $existingPaymentMethods = $paymentMethodRepository->findBy(['donor' => $donor]);
        foreach ($existingPaymentMethods as $existingPaymentMethod) {
            $this->entityManager->remove($existingPaymentMethod);
        }

        $donor->projects->clear();

        foreach ($projectIds as $projectId) {
            $project = $projectRepository->find($projectId);
            if (!$project) {
                continue;
            }

            foreach ($data['paymentData'] as $type => $payment) {
                $paymentMethod = new PaymentMethod();
                $paymentMethod->donor = $donor;
                $paymentMethod->project = $project;
                $paymentMethod->type = (int) $type;
                $paymentMethod->amount = $payment['amount'];
                $paymentMethod->currency = $payment['currency'];
                // This endpoint only ever saves the standing monthly pledge — the one-time
                // action creates instructions directly and does not come through here.
                $paymentMethod->monthly = 1;
                $this->entityManager->persist($paymentMethod);
            }

            $donor->projects->add($project);
        }

        $this->entityManager->flush();
    }

//    public function fetchForMapping()
//    {
//        $sql = "SELECT *,
//(SELECT IFNULL(SUM(amount), 0) FROM `transaction` WHERE email = d.email AND archived = 0) as sumPaid,
//amount - (SELECT IFNULL(SUM(amount), 0) FROM `transaction` WHERE email = d.email AND archived = 0) as amountLeft
// FROM solid.donor d HAVING amountLeft > 0
//         ORDER BY amountLeft DESC";
//        //@TODO add period
//        $stmt = $this->entityManager->getConnection()->prepare($sql);
//        /* @var \Doctrine\DBAL\Result $result */
//        $result = $stmt->executeQuery();
//
//        return $result->fetchAllAssociative();
//    }

//    public function getColumnsToCount(): array
//    {
//        return ['amount'];
//    }
}