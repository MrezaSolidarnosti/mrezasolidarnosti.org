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

    public function getDonorsByProject($project): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('d')
            ->from(Donor::class, 'd')
            ->innerJoin('d.projects', 'p')
            ->where('p.id = :projectId')
            ->andWhere('d.isActive = 1')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('projectId', $project->id)
            ->setParameter('statuses', [Donor::STATUS_VERIFIED, Donor::STATUS_NEW])
            ->orderBy('d.id', 'ASC');
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