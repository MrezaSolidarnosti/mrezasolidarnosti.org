<?php
namespace Solidarity\Delegate\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\Core\Login\Repository\LoginRepositoryInterface;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Factory\DelegateFactory;
use Skeletor\Core\TableView\Repository\TableViewRepository;
use Solidarity\School\Entity\School;
use Solidarity\School\Entity\SchoolType;

class DelegateRepository extends TableViewRepository implements LoginRepositoryInterface
{
    const ENTITY = Delegate::class;
    const FACTORY = DelegateFactory::class;

	/*
	 * return DelegateRepository
	 */
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    /**
     * @throws NotFoundException
     */
    public function findByEmail(string $email)
    {
        $delegate = $this->entityManager->getRepository(static::ENTITY)->findBy(['email' => $email]);
        if (!isset($delegate[0])) {
            throw new NotFoundException();
        }
        return $delegate[0];
    }

    public function updateLoginInfo($model)
    {
        $this->entityManager->persist($model);
        $this->entityManager->flush();
    }

    public function updatePassword($userId, $password) { /* no-op, delegates use magic-link */ }

    public function getJoinableEntities(): array
    {
        return [
            'projects' => 'p',
            'schools'  => 's',
        ];
    }

    public function getAffectedDelegates()
    {
        $sql = "SELECT d.* FROM delegate d WHERE
(SELECT count(*) FROM transaction t WHERE t.beneficiaryId IN (
    SELECT b.id FROM beneficiary b WHERE b.school_id IN (
        SELECT s.id FROM school s WHERE s.delegate_id = d.id
    )
)) > 0";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        /* @var \Doctrine\DBAL\Result $result */
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    public function getSearchableColumns(): array
    {
//        return ['a.email', 'a.name', 'a.schoolName', 'a.comment', 'a.verifiedBy', 'a.city'];
        return ['a.email', 'a.name', 'a.comment', 'a.verifiedBy'];
    }

	public function getAllSchoolTypes(): array {
		$school_types = $this->entityManager
			->getRepository( SchoolType::class )
			->findBy( [], [ 'name' => 'ASC' ] );

		$results = array();

		if ( ! empty( $school_types ) ) {
			$results = array_map( fn( $s ) => $s->name, $school_types );
		}

		return $results;
	}

	public function getAllSchools(): array {
		$schools = $this->entityManager
			->getRepository( School::class )
			->findBy( [], [ 'city' => 'ASC' ] );

		$results = array();

		if ( ! empty( $schools ) ) {
			foreach ( $schools as $school ) {
				$cityName   = $school->city->name;
				$schoolName = $school->name;

				if ( ! isset( $results[ $cityName ] ) ) {
					$results[ $cityName ] = [];
				}

				$results[ $cityName ][] = $schoolName;
			}
		}

		return $results;
	}

}
