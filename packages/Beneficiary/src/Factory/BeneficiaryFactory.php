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
        // Matched by id and updated in place, rather than wiped and reinserted. A delegate whose
        // assigned list no longer contains a registration's project posts -1 for that select, and
        // deleting the row on the strength of an unresolvable select is what used to destroy the
        // registration during an unrelated edit. Only the identity (project/period) falls back to
        // what is stored — the amount input always renders from the model, so a change to it is a
        // real instruction and is applied either way.
        $existing = [];
        foreach ($em->getRepository(RegisteredPeriods::class)->findBy(['beneficiary' => $beneficiaryId]) as $rp) {
            $existing[$rp->getId()] = $rp;
        }

        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);
        $periodRepo = $em->getRepository(\Solidarity\Period\Entity\Period::class);
        $projectRepo = $em->getRepository(\Solidarity\Transaction\Entity\Project::class);

        $submitted = [];
        foreach ($rows as $row) {
            $rowId = (isset($row['id']) && $row['id'] !== '') ? (int) $row['id'] : null;
            $stored = ($rowId !== null && isset($existing[$rowId])) ? $existing[$rowId] : null;

            $period = !empty($row['period']) ? $periodRepo->find($row['period']) : null;
            $project = !empty($row['project']) ? $projectRepo->find($row['project']) : null;

            if ($stored !== null) {
                $stored->period = $period ?? $stored->period;
                $stored->project = $project ?? $stored->project;
                $stored->amount = (int) ($row['amount'] ?? 0);
                $submitted[$stored->getId()] = true;
                continue;
            }

            // A new row still needs a period it can be attached to; the project falls back to the
            // period's own, which is what the form offers when the select is left alone.
            if (!$period) {
                continue;
            }
            $project = $project ?? $period->project;
            if (!$project) {
                continue;
            }

            $rp = new RegisteredPeriods();
            $rp->beneficiary = $beneficiary;
            $rp->period = $period;
            $rp->project = $project;
            $rp->amount = (int) ($row['amount'] ?? 0);
            $em->persist($rp);
        }

        // Pressing Delete takes the row out of the DOM, so it simply is not submitted.
        foreach ($existing as $id => $rp) {
            if (!isset($submitted[$id])) {
                $em->remove($rp);
            }
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

        // No project gate. It resolved a project from the beneficiary's first registered period
        // and skipped every row when there wasn't one — so a beneficiary with no periods had
        // their account number deleted above and never written back. The project it resolved was
        // assigned to nothing either way: PaymentMethod::$project is commented out of the entity.
        foreach ($rows as $row) {
            if (empty($row['type'])) {
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
