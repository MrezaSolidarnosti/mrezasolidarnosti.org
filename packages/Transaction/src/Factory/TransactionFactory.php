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
        $transaction->donorConfirmed = (bool) $data['donorConfirmed'];
        $transaction->comment = $data['comment'] ?? null;
        $transaction->donor = $em->getRepository(Donor::class)->find($data['donor']);
        $transaction->project = $em->getRepository(Project::class)->find($data['project']);
        $transaction->period = $em->getRepository(Period::class)->find($data['period']);
        $transaction->beneficiary = $em->getRepository(Beneficiary::class)->find($data['beneficiary']);
        $paymentType = static::matchPaymentType($transaction->donor, $transaction->beneficiary);
        $transaction->paymentType = $paymentType['paymentType'];
        $transaction->accountNumber = $paymentType['accountNumber'] ?? null;
        $transaction->instructions = $paymentType['instructions'] ?? null;

        $em->persist($transaction);
        $em->flush();

        return $transaction->id;
    }

    public static function compileEntityForUpdate($data, $em)
    {
        $transaction = $em->getRepository(Transaction::class)->find($data['id']);
        $transaction->status = (int) $data['status'];
        $transaction->donorConfirmed = (bool) $data['donorConfirmed'];
        // todo, figure out if amount should be changeable at all?
        if ((int) $data['amount']) {
            $transaction->amount = (int) $data['amount'];
        }
        $transaction->comment = $data['comment'] ?? null;

        return $transaction->id;
    }

    public static function matchPaymentType(Donor $donor, Beneficiary $beneficiary)
    {
        foreach ($donor->paymentMethods as $pm) {
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
