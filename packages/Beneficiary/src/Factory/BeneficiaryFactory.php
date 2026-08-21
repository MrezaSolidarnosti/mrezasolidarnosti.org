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

    /**
     * Reconcile the submitted rows against the stored ones **by id**, rather than wiping the
     * lot and rebuilding from the form.
     *
     * The form cannot always express what is stored. The clearest case: a delegate editing a
     * beneficiary registered under a project that is not on their own assigned list gets a
     * project <select> with no matching <option>, so it posts back the placeholder's -1. The
     * old delete-then-reinsert then found no project for that row, skipped it, and the
     * registration — with the amount attached to it — was gone. Nothing failed, nothing was
     * logged, and the edit that triggered it was usually something unrelated like a phone
     * number.
     *
     * So: a row that arrives with an id updates the stored row, falling back to what is
     * stored for whatever the form could not express; a row without one is new; and only a
     * stored row that was **not submitted at all** — i.e. the user pressed Delete — is
     * removed. Rows also keep their identity across an ordinary save now, instead of every
     * edit churning ids and timestamps.
     */
    private static function syncRegisteredPeriods(int $beneficiaryId, array $rows, EntityManagerInterface $em): void
    {
        /** @var array<int, RegisteredPeriods> $stored */
        $stored = [];
        foreach ($em->getRepository(RegisteredPeriods::class)->findBy(['beneficiary' => $beneficiaryId]) as $rp) {
            $stored[$rp->getId()] = $rp;
        }

        $beneficiary = $em->getRepository(Beneficiary::class)->find($beneficiaryId);
        $submitted = [];

        foreach ($rows as $row) {
            $current = $stored[(int) ($row['id'] ?? 0)] ?? null;

            $period = !empty($row['period'])
                ? $em->getRepository(\Solidarity\Period\Entity\Period::class)->find($row['period'])
                : null;
            $project = !empty($row['project'])
                ? $em->getRepository(\Solidarity\Transaction\Entity\Project::class)->find($row['project'])
                : null;

            if ($current) {
                // Anything unresolvable keeps its stored value. The amount is applied either
                // way — that input renders from the model and is always editable, so an edit
                // to it is a real instruction even when the selects came back unusable.
                $current->period = $period ?? $current->period;
                $current->project = $project ?? $current->project;
                $current->amount = (int) $row['amount'];
                $submitted[$current->getId()] = true;
                continue;
            }

            // A new row has nothing to fall back to, so it still needs both ends. The project
            // may be inferred from the period, as before.
            $project ??= $period?->project;
            if (!$period || !$project) {
                continue;
            }

            $rp = new RegisteredPeriods();
            $rp->beneficiary = $beneficiary;
            $rp->period = $period;
            $rp->project = $project;
            $rp->amount = (int) $row['amount'];
            $em->persist($rp);
        }

        foreach ($stored as $id => $rp) {
            if (!isset($submitted[$id])) {
                $em->remove($rp);
            }
        }

        $em->flush();
    }

    /**
     * Unlike the registered periods above, this really is a rebuild — and safely so. The
     * form renders **all four payment types unconditionally** as checkboxes, so a submission
     * always describes the complete desired state and an unchecked box is a deliberate
     * removal. There is no "the form could not express this" case to preserve.
     *
     * What was here before: the rows were gated on resolving a project — defaulting to the
     * one from the beneficiary's first registered period — and skipped when none was found.
     * `PaymentMethod::$project` has been commented out of the entity for some time, so that
     * project was computed, checked, and then never assigned to anything. Its only remaining
     * effect was that a beneficiary reaching this with no registered periods had every
     * payment method deleted and none put back: the account number gone, silently, in the
     * same flush. Nothing reaches it that way today only because the validator insists on at
     * least one registered period — an unrelated rule in another file. Removed rather than
     * left resting on that.
     */
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
