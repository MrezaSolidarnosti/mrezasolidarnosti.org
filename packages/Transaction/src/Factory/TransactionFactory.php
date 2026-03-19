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
        $transaction->status = (int) $data['status'];
        $transaction->donorConfirmed = (bool) $data['donorConfirmed'];
        $transaction->comment = $data['comment'] ?? null;
        $transaction->donor = $em->getRepository(Donor::class)->find($data['donor']);
        $transaction->project = $em->getRepository(Project::class)->find($data['project']);
        $transaction->period = $em->getRepository(Period::class)->find($data['period']);
        $transaction->beneficiary = $em->getRepository(Beneficiary::class)->find($data['beneficiary']);

        // Get account number from beneficiary's payment methods (first bank transfer)
        $transaction->accountNumber = null;
        if ($transaction->beneficiary) {
            foreach ($transaction->beneficiary->paymentMethods as $pm) {
                if ($pm->accountNumber) {
                    $transaction->accountNumber = $pm->accountNumber;
                    break;
                }
            }
        }

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
}
