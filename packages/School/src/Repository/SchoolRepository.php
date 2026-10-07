<?php
namespace Solidarity\School\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\TableView\Repository\TableViewRepository;
use Solidarity\School\Entity\School;
use Solidarity\School\Factory\SchoolFactory;

class SchoolRepository extends TableViewRepository
{
    const ENTITY = School::class;
    const FACTORY = SchoolFactory::class;

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager);
    }

    /** `d` lets the table filter on delegates: `d.id => <id>` or `d.id => not_null`. */
    public function getJoinableEntities(): array
    {
        return ['delegates' => 'd'];
    }

    public function getSearchableColumns(): array
    {
        return ['a.name'];
    }

    public function getByNameAndCity($schoolName, $cityName)
    {
        $sql = "SELECT s.id FROM `school` s JOIN `city` c ON (s.city_id = c.id) WHERE s.name = :schoolName AND c.name = :cityName";
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->bindValue(':schoolName', $schoolName);
        $stmt->bindValue(':cityName', $cityName);

        $id = $stmt->executeQuery()->fetchOne();
        if (!$id) {
            return false;
        }

        return $this->getById($id);
    }

}