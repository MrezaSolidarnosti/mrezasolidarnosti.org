<?php

namespace Solidarity\Backend\Action;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Laminas\Config\Config;
use \League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Laminas\Session\ManagerInterface as Session;
use Skeletor\Core\Mapper\NotFoundException;
use Solidarity\Beneficiary\Service\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Donor\Service\Donor;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project;
use Tamtamchik\SimpleFlash\Flash;

class CreateTransaction extends Html
{
    const MIN_TRANSACTION_DONATION_AMOUNT = 500;
    const MAX_YEARLY_DONATION_AMOUNT = 50000;

    /**
     * HomeAction constructor.
     * @param Logger $logger
     * @param Config $config
     * @param Engine $template
     */
    public function __construct(
        Logger $logger, Config $config, Engine $template, private Session $session, private Flash $flash,
        public readonly EntityManagerInterface $entityManager, public readonly TransactionService $transaction,
        public readonly Beneficiary $beneficiary, public readonly Project $project, public readonly Donor $donor
    ) {
        parent::__construct($logger, $config, $template);
    }

    /**
     * Parses data for provided merchantId
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request request
     * @param \Psr\Http\Message\ResponseInterface $response response
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        if ($this->isHoliday()) {
            $this->getLogger()->log(\Monolog\Level::Info, 'Holiday detected. Not creating transactions.');
            return $response;
        }

        $projects = $this->project->getEntities([], 2);

        // todo should be no more than 1 period for processing per project ?
        foreach ($projects as $project) {

            foreach ($project->periods as $period) {
                if ($period->processing) {
                    foreach ($this->donor->getDonorsByProject($project) as $donor) {
                        $this->getLogger()->log(\Monolog\Level::Info, sprintf('Processing donor %s at %s', $donor->email, date('Y-m-d H:i:s')));

                        // Check yearly max across all payment types for this project
//                        $totalDonatedForProject = $this->transaction->getPaidSumAmountForDonorPerProject($donor, $project);
//                        $maxYearlyRsd = static::MAX_YEARLY_DONATION_AMOUNT;
//                        $yearlyRemaining = $maxYearlyRsd - $totalDonatedForProject;
                        // todo check this limit
//                        if ($yearlyRemaining <= static::MIN_TRANSACTION_DONATION_AMOUNT) {
//                            $this->getLogger()->log(\Monolog\Level::Info, sprintf('Yearly max reached for donor %s on project %s', $donor->email, $project->code));
//                            continue;
//                        }
                        // scroll through available payment methods for donor
                        foreach ($donor->getPaymentMethodsForProject($project) as $donorPM) {
                            // donated so far for current payment type and project
                            $donatedSoFar = $this->transaction->getPaidSumAmountForDonorPerProject($donor, $project, $donorPM->type);
                            // Donor amount is in original currency, convert EUR to RSD for calculation
                            $pledgedAmountRsd = $donorPM->type === 1
                                ? $donorPM->amount
                                : Transaction::eurToRsd($donorPM->amount);
                            $remainingFromDonor = $pledgedAmountRsd - $donatedSoFar;
                            $this->create($donor, $project, $remainingFromDonor, $period, $donorPM->type);

                            //todo send notification mail to donor about new instruction

                        }
                    }
                }
            }
        }

        echo 'success';
        die();

        return $response->withStatus(302)->withHeader('Location', $url);
    }

    /**
     * Creates transactions for donor. Returns total amount allocated (in RSD).
     */
    public function create($donor, $project, $amountToDonate, $period, int $paymentType): int
    {
        $minTransactionDonationAmount = static::MIN_TRANSACTION_DONATION_AMOUNT;
        if ($amountToDonate < $minTransactionDonationAmount) {
            $this->getLogger()->log(\Monolog\Level::Info, sprintf('SKIP - Not creating for amounts less than %d', $minTransactionDonationAmount));
            return 0;
        }
        if ($amountToDonate > 100000) {
            $minTransactionDonationAmount = 10000;
        }

        $totalAllocated = 0;
        foreach ($this->beneficiary->getByPeriod($period->id) as $beneficiary) {
            // handle donor choices
            if ($project->code === 'MSP') {
                // if donor wants school skip school type 9 an 17
                if ($donor->wantsToDonateTo === \Solidarity\Donor\Entity\Donor::DONATE_TO_SCHOOL
                    && ($beneficiary->school->type->id === 9 || $beneficiary->school->type->id === 17)) {
                    $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                        'SKIP - Donor %s has not selected school type for beneficiary %s', $donor->email, $beneficiary->name
                    ));
                    continue;
                }
                // if donor wants uni skip all school type but 9 an 17
                if ($donor->wantsToDonateTo === \Solidarity\Donor\Entity\Donor::DONATE_TO_UNI) {
                    if ($beneficiary->school->type->id !== 9 && $beneficiary->school->type->id !== 17) {
                        $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                            'SKIP - Donor %s has not selected uni type for beneficiary %s', $donor->email, $beneficiary->name
                        ));
                        continue;
                    }
                }
            }

            // Match payment method type between donor and beneficiary
            $beneficiaryPM = $this->getMatchingPaymentMethod($beneficiary, $paymentType);
            if (!$beneficiaryPM) {
                $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                    'SKIP - No matching payment method type %d for beneficiary %s', $paymentType, $beneficiary->name
                ));
                continue;
            }

            $receivedSoFar = $this->transaction->getSumAmountForBeneficiary($beneficiary, $project, $period);
            $totalAmount = $beneficiary->getAmountForPeriod($period);
            $beneficiaryRemaining = $totalAmount - $receivedSoFar;
            if ($beneficiaryRemaining <= static::MIN_TRANSACTION_DONATION_AMOUNT) {
                $this->getLogger()->log(\Monolog\Level::Info, sprintf('SKIP - Beneficiary %s needs less than %d', $beneficiary->name, static::MIN_TRANSACTION_DONATION_AMOUNT));
                continue;
            }

            // Cap by per-person limit (donor → beneficiary across all projects)
            // todo can add per year here, this is yearly limit
            $perPersonRemaining = $this->transaction->getRemainingPerPersonLimit($donor, $beneficiary);
            if ($perPersonRemaining <= 0) {
                $this->getLogger()->log(\Monolog\Level::Info, sprintf('SKIP - Per-person limit achieved. Donor %s. Beneficiary %s', $donor->email, $beneficiary->name));
                continue;
            }
            // Transaction amount is the minimum of: donor remaining, beneficiary remaining, per-person limit remaining
            $transactionAmount = min($amountToDonate, $beneficiaryRemaining, $perPersonRemaining);

            // Resolve account info from beneficiary's payment method
            $accountNumber = null;
            $instructions = null;
            $amountEur = 0;
            if ($paymentType === BeneficiaryPaymentMethod::TYPE_BANK_TRANSFER) {
                $accountNumber = $beneficiaryPM->accountNumber;
            } else {
                $amountEur = Transaction::rsdToEur($transactionAmount);
                $instructions = $beneficiaryPM->wireInstructions;
            }

            $transaction = $this->transaction->create([
                'donor' => $donor,
                'project' => $project,
                'amount' => $transactionAmount,
                'amountEur' => $amountEur,
                'period' => $period,
                'comment' => '',
                'status' => Transaction::STATUS_NEW,
                'donorConfirmed' => 0,
                'beneficiary' => $beneficiary,
                'paymentType' => $paymentType,
                'accountNumber' => $accountNumber,
                'instructions' => $instructions,
                // @TODO solve this cleaner, add check to filter, when ran from cli csrf is not required
                'skipCsrf' => true,
            ]);
            $this->getLogger()->log(\Monolog\Level::Info, sprintf(
                    'Created trx for donor %s for amount %s at %s', $donor->email, $transactionAmount, date('Y-m-d H:i:s'))
            );
            $amountToDonate -= $transactionAmount;
            $totalAllocated += $transactionAmount;
            if ($amountToDonate < $minTransactionDonationAmount) {
                break;
            }
        }
        return $totalAllocated;
    }

    private function getMatchingPaymentMethod($beneficiary, int $paymentType): ?BeneficiaryPaymentMethod
    {
        foreach ($beneficiary->paymentMethods as $pm) {
            if ($pm->type === $paymentType) {
                return $pm;
            }
        }
        return null;
    }

    public function isHoliday(): bool
    {
        $dates = ['01.01', '02.01', '06.01', '07.01', '15.01', '16.01', '17.01', '20.01', '01.05', '02.05', '06.05', '06.12', '11.11', '25.12', '31.12'];

        return in_array(date('d.m'), $dates);
    }

}