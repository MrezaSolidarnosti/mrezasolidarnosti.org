<?php

namespace Solidarity\Beneficiary\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Factory\AbstractFactory;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;

class BeneficiaryFactory extends AbstractFactory
{
    public static function compileEntityForCreate($data, EntityManagerInterface $em)
    {
        $registeredPeriodsData = $data['registeredPeriods'] ?? [];
        unset($data['registeredPeriods']);
        $paymentMethodsData = $data['paymentMethods'] ?? [];
        unset($data['paymentMethods']);

        $entityId = parent::compileEntityForCreate($data, $em);

        static::syncRegisteredPeriods($entityId, $registeredPeriodsData, $em);
        static::syncPaymentMethods($entityId, $paymentMethodsData, $em);

        return $entityId;
    }

    public static function compileEntityForUpdate($data, $em)
    {
        $registeredPeriodsData = $data['registeredPeriods'] ?? [];
        unset($data['registeredPeriods']);
        $paymentMethodsData = $data['paymentMethods'] ?? [];
        unset($data['paymentMethods']);

        $entityId = parent::compileEntityForUpdate($data, $em);

        static::syncRegisteredPeriods($entityId, $registeredPeriodsData, $em);
        static::syncPaymentMethods($entityId, $paymentMethodsData, $em);

        return $entityId;
    }

    private static function syncRegisteredPeriods(int $beneficiaryId, array $rows, EntityManagerInterface $em): void
    {
        $existing = $em->getRepository(RegisteredPeriods::class)
            ->findBy(['beneficiary' => $beneficiaryId]);
        foreach ($existing as $rp) {
            $em->remove($rp);
        }
        $em->flush();

        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);

        foreach ($rows as $row) {
            $period = $em->getRepository(\Solidarity\Period\Entity\Period::class)
                ->find($row['period']);
            if (!$period) {
                continue;
            }

            $project = !empty($row['project'])
                ? $em->getRepository(\Solidarity\Transaction\Entity\Project::class)->find($row['project'])
                : $period->project;
            if (!$project) {
                continue;
            }

            $rp = new RegisteredPeriods();
            $rp->beneficiary = $beneficiary;
            $rp->period = $period;
            $rp->project = $project;
            $rp->amount = $row['amount'];
            $em->persist($rp);
        }
        $em->flush();
    }

    private static function syncPaymentMethods(int $beneficiaryId, $rows, EntityManagerInterface $em): void
    {
        if (!is_iterable($rows)) {
            return;
        }

        $existing = $em->getRepository(PaymentMethod::class)
            ->findBy(['beneficiary' => $beneficiaryId]);
        foreach ($existing as $pm) {
            $em->remove($pm);
        }
        $em->flush();

        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);

        // Resolve default project from beneficiary's first registered period
        $defaultProject = null;
        $registeredPeriods = $em->getRepository(RegisteredPeriods::class)
            ->findBy(['beneficiary' => $beneficiaryId]);
        if (!empty($registeredPeriods)) {
            $defaultProject = $registeredPeriods[0]->project;
        }

        foreach ($rows as $row) {
            if (empty($row['type'])) {
                continue;
            }
            $project = !empty($row['project'])
                ? $em->getRepository(\Solidarity\Transaction\Entity\Project::class)->find($row['project'])
                : $defaultProject;
            if (!$project) {
                continue;
            }

            $pm = new PaymentMethod();
            $pm->beneficiary = $beneficiary;
            $pm->type = (int) $row['type'];
            $pm->accountNumber = $row['accountNumber'] ?? null;
            $pm->wireInstructions = $row['wireInstructions'] ?? null;
            $em->persist($pm);
        }
        $em->flush();
    }
}
