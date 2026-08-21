<?php
namespace Solidarity\Backend\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\School\Service\City;
use Solidarity\School\Service\School;
use Solidarity\School\Entity\SchoolType as SchoolTypeEntity;
use Solidarity\School\Service\SchoolType;
use Solidarity\Transaction\Entity\Transaction;
use Tamtamchik\SimpleFlash\Flash;

class SchoolController extends AjaxCrudController
{
    const TITLE_VIEW = "View school";
    const TITLE_CREATE = "Create new school";
    const TITLE_UPDATE = "Edit school: ";
    const TITLE_UPDATE_SUCCESS = "School updated successfully.";
    const TITLE_CREATE_SUCCESS = "School created successfully.";
    const TITLE_DELETE_SUCCESS = "School deleted successfully.";
    const PATH = 'School';

    /**
     * @param School $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        School $service, Session $session, Config $config, Flash $flash, Engine $template, private City $city,
        private SchoolType $schoolType, private EntityManagerInterface $em, private Delegate $delegate
    ) {
        parent::__construct($service, $session, $config, $flash, $template);

        if ($this->getSession()->getStorage()->offsetGet('loggedInEntityType') === 'delegate') {
            $this->tableViewConfig['createButton'] = false;
        }
    }

    private function isDelegateSession(): bool
    {
        return $this->getSession()->getStorage()->offsetGet('loggedInEntityType') === 'delegate';
    }

    private function getDelegateSchoolIds(): array
    {
        $delegateId = $this->getSession()->getStorage()->offsetGet('loggedIn');
        $delegate = $this->delegate->getById($delegateId);
        $ids = [];
        foreach ($delegate->schools as $school) {
            $ids[] = $school->id;
        }
        return $ids;
    }

    public function form(): Response
    {
        $this->formData['cities'] = $this->city->getFilterData();
        $this->formData['types'] = $this->schoolType->getFilterData();

        $id = $this->getRequest()->getAttribute('id');

        // Delegates can only view their own schools
        if ($this->isDelegateSession() && $id) {
            $allowedIds = $this->getDelegateSchoolIds();
            if (!in_array((int) $id, $allowedIds)) {
                return $this->redirect('/school/view/');
            }
        }

        $this->formData['readOnly'] = $this->isDelegateSession();

        $schoolStats = [];
        if ($id) {
            $schoolStats = $this->getSchoolStatsByPeriod((int) $id);
        }
        $this->formData['schoolStats'] = $schoolStats;

        return parent::form();
    }

    public function tableHandler()
    {
        if ($this->isDelegateSession()) {
            $this->uncountableFilters['delegate'] = $this->getSession()->getStorage()->offsetGet('loggedIn');
        }
        return parent::tableHandler();
    }

    private function getSchoolStatsByPeriod(int $schoolId): array
    {
        // Get all periods that have beneficiaries from this school
        $periods = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(rp.period) as periodId')
            ->from(RegisteredPeriods::class, 'rp')
            ->innerJoin('rp.beneficiary', 'b')
            ->where('b.school = :schoolId')
            ->andWhere('b.status != :deleted')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('deleted', Beneficiary::STATUS_DELETED)
            ->getQuery()->getArrayResult();

        $periodIds = array_column($periods, 'periodId');
        if (empty($periodIds)) {
            return [];
        }

        $periodEntities = $this->em->getRepository(\Solidarity\Period\Entity\Period::class)
            ->findBy(['id' => $periodIds], ['year' => 'DESC', 'month' => 'DESC']);

        $stats = [];
        foreach ($periodEntities as $period) {
            // Beneficiary count
            $benCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(DISTINCT rp.beneficiary)')
                ->from(RegisteredPeriods::class, 'rp')
                ->innerJoin('rp.beneficiary', 'b')
                ->where('b.school = :schoolId')
                ->andWhere('rp.period = :periodId')
                ->andWhere('b.status != :deleted')
                ->setParameter('schoolId', $schoolId)
                ->setParameter('periodId', $period->getId())
                ->setParameter('deleted', Beneficiary::STATUS_DELETED)
                ->getQuery()->getSingleScalarResult();

            // Total requested amount
            $requestedAmount = (int) $this->em->createQueryBuilder()
                ->select('COALESCE(SUM(rp.amount), 0)')
                ->from(RegisteredPeriods::class, 'rp')
                ->innerJoin('rp.beneficiary', 'b')
                ->where('b.school = :schoolId')
                ->andWhere('rp.period = :periodId')
                ->andWhere('b.status != :deleted')
                ->setParameter('schoolId', $schoolId)
                ->setParameter('periodId', $period->getId())
                ->setParameter('deleted', Beneficiary::STATUS_DELETED)
                ->getQuery()->getSingleScalarResult();

            // Transaction stats by status
            $trxStats = [];
            $statusMap = [
                'confirmed' => Transaction::STATUS_CONFIRMED,
                'paid' => Transaction::STATUS_PAID,
                'active' => Transaction::STATUS_NEW,
                'cancelled' => Transaction::STATUS_CANCELLED,
            ];
            foreach ($statusMap as $key => $status) {
                $qb = $this->em->createQueryBuilder()
                    ->select('COALESCE(SUM(t.amount), 0) as total, COUNT(t.id) as cnt')
                    ->from(Transaction::class, 't')
                    ->innerJoin('t.beneficiary', 'b')
                    ->where('b.school = :schoolId')
                    ->andWhere('t.period = :periodId')
                    ->andWhere('t.status = :status')
                    ->setParameter('schoolId', $schoolId)
                    ->setParameter('periodId', $period->getId())
                    ->setParameter('status', $status);
                $row = $qb->getQuery()->getSingleResult();
                $trxStats[$key . 'Amount'] = (int) $row['total'];
                $trxStats[$key . 'Count'] = (int) $row['cnt'];
            }

            $stats[] = [
                'period' => $period,
                'beneficiaryCount' => $benCount,
                'requestedAmount' => $requestedAmount,
            ] + $trxStats;
        }

        return $stats;
    }

    public function import()
    {
        ini_set('max_execution_time', 1200);
        foreach ($this->getConfig()->offsetGet('schoolsMap') as $key => $values) {
            $city = $this->city->create(['name' => $key]);
            foreach ($values as $value) {
                $type = null;
                if (str_contains($value, 'Osnovna') || str_contains($value, 'osnovna')) {
                    $type = $this->schoolType->getEntities(['name' => 'Osnovna škola'])[0];
                }
                if (str_contains($value, 'Srednja') || str_contains($value, 'srednja')) {
                    $type = $this->schoolType->getEntities(['name' => 'Srednja škola'])[0];
                }
                if (str_contains($value, 'Gimnazija') || str_contains($value, 'gimnazija')) {
                    $type = $this->schoolType->getEntities(['name' => 'Gimnazija'])[0];
                }
                if (str_contains($value, 'osnovno i srednje')) {
                    $type = $this->schoolType->getEntities(['name' => 'Škola za osnovno i srednje obrazovanje'])[0];
                }
                if (str_contains($value, 'balet') || str_contains($value, 'uzičk') || str_contains($value, 'balet') || str_contains($value, 'Balet')
                    || str_contains($value, 'primenjen')) {
                    $type = $this->schoolType->getEntities(['name' => 'Umetnička škola'])[0];
                }
                if (str_contains($value, 'ekonomsk') || str_contains($value, 'tehničk') || str_contains($value, 'Tehničk')
                || str_contains($value, 'poljoprivredn') || str_contains($value, 'Poljoprivredn') || str_contains($value, 'brodogradnju') || str_contains($value, 'Ekonomsk')
                || str_contains($value, 'Građevins') || str_contains($value, 'građevins') || str_contains($value, 'medicin') || str_contains($value, 'Medicin')  || str_contains($value, 'tručna')  || str_contains($value, 'gostitelj')
                    || str_contains($value, 'ašinsk') || str_contains($value, 'ehnološk')  || str_contains($value, 'aobraćajna') || str_contains($value, 'etnička')  || str_contains($value, 'izajn') || str_contains($value, 'oslovn')  || str_contains($value, 'emijsk') || str_contains($value, 'govačka')) {
                    $type = $this->schoolType->getEntities(['name' => 'Srednja stručna škola'])[0];
                }
                $this->service->create([
                    'name' => $value,
                    // Ids keyed by property name, not entities keyed by column name:
                    // AbstractFactory resolves a relation by handing the value to find(),
                    // and looks the key up as the entity's property - so 'schoolType' never
                    // matched School::$type and these objects were never valid identifiers.
                    'city' => $city->getId(),
                    // School::$type is not nullable; a name matching none of the patterns above
                    // is parked under the placeholder rather than dropped.
                    'type' => ($type ?? $this->placeholderSchoolType())->getId(),
                ]);
            }
        }


        die('done all');
    }

    /**
     * The "unknown type" row, created on first use.
     */
    private function placeholderSchoolType(): SchoolTypeEntity
    {
        $repository = $this->em->getRepository(SchoolTypeEntity::class);
        $type = $repository->findOneBy(['name' => SchoolTypeEntity::PLACEHOLDER]);

        if ($type === null) {
            $type = new SchoolTypeEntity();
            $type->name = SchoolTypeEntity::PLACEHOLDER;
            $this->em->persist($type);
            $this->em->flush();
        }

        return $type;
    }
}