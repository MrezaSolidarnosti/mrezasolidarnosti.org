<?php
namespace Solidarity\Transaction\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Factory\AbstractFactory;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

class TransactionFactory extends AbstractFactory
{
    public static function compileEntityForCreate($data, $em): ?int
    {
        $transaction = new Transaction();
        $transaction->amount = (int) $data['amount'];
        $transaction->amountEur = (int) $data['amountEur'];
        $transaction->status = (int) $data['status'];
        $transaction->comment = $data['comment'] ?? null;
        $transaction->manual = (bool) ($data['manual'] ?? false);
        $transaction->donor = $em->getRepository(Donor::class)->find($data['donor']);
        $transaction->project = $em->getRepository(Project::class)->find($data['project']);
        $transaction->period = $em->getRepository(Period::class)->find($data['period']);
        $transaction->beneficiary = $em->getRepository(Beneficiary::class)->find($data['beneficiary']);
        // The allocator already resolved the payment type (by the donor's chosen types on
        // demand, or pledged methods for the cron) and the beneficiary's account/instructions
        // — trust it. Only fall back to re-deriving from persisted methods for callers that
        // don't supply one (e.g. legacy imports).
        if (!empty($data['paymentType'])) {
            $transaction->paymentType = (int) $data['paymentType'];
            $transaction->accountNumber = $data['accountNumber'] ?? null;
            $transaction->instructions = $data['instructions'] ?? null;
        } else {
            $match = static::matchPaymentType($transaction->donor, $transaction->beneficiary, $transaction->project);
            $transaction->paymentType = $match['paymentType'];
            $transaction->accountNumber = $match['accountNumber'] ?? null;
            $transaction->instructions = $match['instructions'] ?? null;
        }

        $em->persist($transaction);
        $em->flush();

        return $transaction->id;
    }

    public static function compileEntityForUpdate($data, $em)
    {
        $transaction = $em->getRepository(Transaction::class)->find($data['id']);
        $transaction->status = (int) $data['status'];
        // amount is not changeable. use case is to cancel it and create new one
        $transaction->comment = $data['comment'] ?? null;

        return $transaction->id;
    }

    /**
     * Find a payment type both parties share. When $project is given, only the donor's
     * payment methods pledged to that project are considered — a donor can pledge
     * different types to different projects, so the type must match the project the
     * transaction is being created for, not just any of the donor's pledges.
     */
    public static function matchPaymentType(Donor $donor, Beneficiary $beneficiary, ?Project $project = null)
    {
        $donorPaymentMethods = $project
            ? $donor->getPaymentMethodsForProject($project)
            : $donor->paymentMethods;

        foreach ($donorPaymentMethods as $pm) {
            foreach ($beneficiary->paymentMethods as $bPm) {
                if ($pm->type === $bPm->type) {
                    return [
                        'paymentType' => $pm->type,
                        'accountNumber' => $bPm->accountNumber,
                        'instructions' => $bPm->wireInstructions,
                    ];
                }
            }
        }
        throw new \Exception('No matching payment type found');
    }
}
