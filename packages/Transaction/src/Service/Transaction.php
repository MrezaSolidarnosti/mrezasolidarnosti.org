<?php
namespace Solidarity\Transaction\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction as TransactionEntity;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Period\Repository\PeriodRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;

class Transaction extends TableView
{
    /** Below this RSD amount we stop allocating (too small to be worth an instruction). */
    const MIN_TRANSACTION_DONATION_AMOUNT = 500;

    /**
     * @param TransactionRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        TransactionRepository $repo, Session $user, Logger $logger, TransactionFilter $filter, private ProjectService $project,
        private BeneficiaryRepository $beneficiaryRepo, private PeriodRepository $periodRepo,
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    /**
     * Is there at least one active beneficiary in a processing period whose registered
     * need for that period is not yet covered by allocated transactions? Gates the donor's
     * on-demand donation button, so it uses the SAME period set (processing — the periods
     * open for transaction creation) and the same unmet-need math as createForDonor — if
     * the button shows, createForDonor has something to allocate.
     *
     * "Covered" uses the allocated statuses (NEW, WAITING_CONFIRMATION, CONFIRMED, PAID)
     * via getSumAmountForBeneficiary — same as allocateToBeneficiary — so a need already
     * pledged by pending instructions is treated as met. Short-circuits on the first hit.
     */
    public function hasUnmetNeeds(): bool
    {
        foreach ($this->periodRepo->fetchProcessing() as $period) {
            foreach ($this->beneficiaryRepo->fetchByPeriod($period->getId()) as $beneficiary) {
                $received = $this->repo->getSumAmountForBeneficiary($beneficiary, null, $period);
                $remaining = $beneficiary->getAmountForPeriod($period) - $received;
                if ($remaining > self::MIN_TRANSACTION_DONATION_AMOUNT) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getSumAmountForBeneficiary(Beneficiary $beneficiary, ?Project $project = null, ?Period $period = null) {
        return $this->repo->getSumAmountForBeneficiary($beneficiary, $project, $period);
    }

    public function getTotalNetworkedAmount(): int
    {
        return $this->repo->getTotalNetworkedAmount();
    }

    public function getPaidSumAmountForDonorPerProject(Donor $donor, Project $project, ?int $paymentType = null)
    {
        return $this->repo->getPaidSumAmountForDonorPerProject($donor, $project, $paymentType);
    }

    public function getPaidSumAmountForDonor(Donor $donor): int
    {
        return $this->repo->getPaidSumAmountForDonor($donor);
    }

    public function getTransactionCountForDonor(Donor $donor): int
    {
        return $this->repo->getTransactionCountForDonor($donor);
    }

    /**
     * @return TransactionEntity[]
     */
    public function getInstructionsForDonor(Donor $donor, int $offset, int $limit): array
    {
        return $this->repo->getInstructionsForDonor($donor, $offset, $limit);
    }

    public function getInstructionsCountForDonor(Donor $donor): int
    {
        return $this->repo->getInstructionsCountForDonor($donor);
    }

    private const LOCKED_STATUSES = [
        TransactionEntity::STATUS_CONFIRMED,
        TransactionEntity::STATUS_CANCELLED,
        TransactionEntity::STATUS_EXPIRED,
        TransactionEntity::STATUS_PAID,
    ];

    private const VALID_TARGET_STATUSES = [
        TransactionEntity::STATUS_CONFIRMED,
        TransactionEntity::STATUS_CANCELLED,
    ];

    public function updateStatus(int $id, int $newStatus): void
    {
        if (!in_array($newStatus, self::VALID_TARGET_STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid target status.');
        }

        $transaction = $this->repo->getById($id);
        if (!$transaction) {
            throw new \Exception('Transaction not found.');
        }

        if (in_array($transaction->status, self::LOCKED_STATUSES, true)) {
            throw new \Exception(sprintf(
                'Status "%s" ne moze biti promenjen.',
                TransactionEntity::getHrStatus($transaction->status)
            ));
        }

        $this->repo->updateField('status', $newStatus, $id);
    }

    public function getTransactionsBySchool($schoolId)
    {
        return $this->repo->getTransactionsBySchool($schoolId);
    }

    public function getRemainingPerPersonLimit($donor, $beneficiary): int
    {
        return $this->repo->getRemainingPerPersonLimit($donor, $beneficiary);
    }

    /**
     * Allocate $amount (RSD) from $donor to the beneficiaries of a single $period in one
     * $project, paying through $paymentType, until the leftover drops below the minimum.
     * Beneficiaries are served in lowest-share-of-target order. For pledges over 100k the
     * minimum slice rises to 10,000 RSD so large donors don't generate a flood of tiny
     * instructions. Returns the total RSD allocated.
     */
    public function allocateAmount(Donor $donor, Project $project, int $amount, Period $period, int $paymentType): int
    {
        $minSlice = $amount > 100000 ? 10000 : self::MIN_TRANSACTION_DONATION_AMOUNT;
        if ($amount < $minSlice) {
            return 0;
        }

        $totalAllocated = 0;
        foreach ($this->beneficiaryRepo->fetchByPeriod($period->id) as $beneficiary) {
            $result = $this->allocateToBeneficiary(
                $donor, $project, $period, $beneficiary, [$paymentType => $amount], $minSlice
            );
            if ($result['amount'] > 0) {
                $amount -= $result['amount'];
                $totalAllocated += $result['amount'];
                if ($amount < $minSlice) {
                    break;
                }
            }
        }

        return $totalAllocated;
    }

    /**
     * On-demand donor allocation: the donor picks $projects and per-payment-type RSD
     * $budgets, and we create instructions to the matching beneficiaries right now.
     *
     * A beneficiary matches when it is registered in one of the $projects' **processing**
     * periods (the ones open for transaction creation — `active` only lets delegates add
     * beneficiaries), has a payment method of a type the donor is funding (and that type
     * still has budget), and still has an unmet need for that period. Donor-triggered and
     * one-time — same period set as the cron, just initiated on demand instead of scheduled.
     *
     * Distribution stays balanced two ways:
     *  - across projects: a round-robin gives one allocation to each project per pass;
     *  - across beneficiaries: fetchByPeriod returns them least-funded-first and the
     *    cursor advances each pass, so each pass hits a different beneficiary. The
     *    per-person yearly cap limits how much any one beneficiary takes.
     *
     * $budgets is a single per-type pool shared across all selected projects; each
     * allocation decrements the type it actually used.
     *
     * @param Project[] $projects donor-selected projects
     * @param array<int,int> $budgets RSD available per payment type ([type => rsd])
     * @return int total RSD allocated
     */
    public function createForDonor(Donor $donor, array $projects, array $budgets): int
    {
        $minSlice = array_sum($budgets) > 100000 ? 10000 : self::MIN_TRANSACTION_DONATION_AMOUNT;
        $budgets = array_filter($budgets, static fn($rsd) => $rsd >= $minSlice);
        if (!$budgets || !$projects) {
            return 0;
        }

        // One ordered candidate queue per project over its PROCESSING periods.
        $queues = [];
        foreach ($projects as $project) {
            $candidates = [];
            foreach ($project->periods as $period) {
                if (!$period->processing) {
                    continue;
                }
                foreach ($this->beneficiaryRepo->fetchByPeriod($period->id) as $beneficiary) {
                    $candidates[] = ['period' => $period, 'beneficiary' => $beneficiary];
                }
            }
            if ($candidates) {
                $queues[] = ['project' => $project, 'candidates' => $candidates, 'cursor' => 0];
            }
        }

        $totalAllocated = 0;

        while ($queues && array_sum($budgets) >= $minSlice) {
            $allocatedThisPass = false;

            foreach (array_keys($queues) as $qi) {
                $result = ['type' => null, 'amount' => 0];
                // Advance this project's cursor until we allocate or run out of candidates.
                while ($queues[$qi]['cursor'] < count($queues[$qi]['candidates'])) {
                    $candidate = $queues[$qi]['candidates'][$queues[$qi]['cursor']];
                    $queues[$qi]['cursor']++;
                    $result = $this->allocateToBeneficiary(
                        $donor, $queues[$qi]['project'], $candidate['period'], $candidate['beneficiary'],
                        $budgets, $minSlice
                    );
                    if ($result['amount'] > 0) {
                        break;
                    }
                }

                if ($result['amount'] > 0) {
                    $budgets[$result['type']] -= $result['amount']; // spend from the type actually used
                    $totalAllocated += $result['amount'];
                    $allocatedThisPass = true;
                }

                if ($queues[$qi]['cursor'] >= count($queues[$qi]['candidates'])) {
                    unset($queues[$qi]); // project exhausted
                }
            }

            if (!$allocatedThisPass) {
                break;
            }
        }

        return $totalAllocated;
    }

    /**
     * Allocate a donor's per-project pledges across all $projects in a balanced way: each
     * project keeps its own pledged budget (PaymentMethod.amount minus what the donor already
     * paid into that project), and a round-robin gives one beneficiary allocation to each
     * project per pass, so projects progress together instead of one draining first. Money
     * never moves between projects. Beneficiaries are served in lowest-share-of-target order,
     * capped by the per-person yearly limit.
     *
     * @param Project[] $projects all projects to consider (those the donor has not pledged to are skipped)
     * @return int total RSD allocated
     */
    public function createBalancedForDonor(Donor $donor, array $projects): int
    {
        // One track per project the donor pledged to: remaining budget per payment type
        // plus an ordered beneficiary cursor over the project's processing periods.
        $tracks = [];
        foreach ($projects as $project) {
            $budgets = [];
            foreach ($donor->getPaymentMethodsForProject($project) as $pm) {
                $pledgedRsd = $pm->type === PaymentMethod::TYPE_BANK_TRANSFER
                    ? $pm->amount
                    : TransactionEntity::eurToRsd($pm->amount);
                $remaining = $pledgedRsd - $this->repo->getPaidSumAmountForDonorPerProject($donor, $project, $pm->type);
                if ($remaining >= self::MIN_TRANSACTION_DONATION_AMOUNT) {
                    $budgets[$pm->type] = ($budgets[$pm->type] ?? 0) + $remaining;
                }
            }
            if (!$budgets) {
                continue;
            }

            $candidates = [];
            foreach ($project->periods as $period) {
                if (!$period->processing) {
                    continue;
                }
                foreach ($this->beneficiaryRepo->fetchByPeriod($period->id) as $beneficiary) {
                    $candidates[] = ['period' => $period, 'beneficiary' => $beneficiary];
                }
            }
            if (!$candidates) {
                continue;
            }

            $minSlice = array_sum($budgets) > 100000 ? 10000 : self::MIN_TRANSACTION_DONATION_AMOUNT;
            $tracks[] = [
                'project' => $project, 'budgets' => $budgets, 'candidates' => $candidates, 'cursor' => 0, 'minSlice' => $minSlice,
            ];
        }

        $totalAllocated = 0;

        while ($tracks) {
            $allocatedThisPass = false;

            foreach (array_keys($tracks) as $ti) {
                $result = ['type' => null, 'amount' => 0];
                while ($tracks[$ti]['cursor'] < count($tracks[$ti]['candidates'])) {
                    $candidate = $tracks[$ti]['candidates'][$tracks[$ti]['cursor']];
                    $tracks[$ti]['cursor']++;
                    $result = $this->allocateToBeneficiary(
                        $donor, $tracks[$ti]['project'], $candidate['period'], $candidate['beneficiary'],
                        $tracks[$ti]['budgets'], $tracks[$ti]['minSlice']
                    );
                    if ($result['amount'] > 0) {
                        break;
                    }
                }

                if ($result['amount'] > 0) {
                    $tracks[$ti]['budgets'][$result['type']] -= $result['amount'];
                    $totalAllocated += $result['amount'];
                    $allocatedThisPass = true;
                }

                // Drop the track once it is out of beneficiaries or out of usable budget.
                $hasBudget = false;
                foreach ($tracks[$ti]['budgets'] as $budget) {
                    if ($budget >= $tracks[$ti]['minSlice']) {
                        $hasBudget = true;
                        break;
                    }
                }
                if (!$hasBudget || $tracks[$ti]['cursor'] >= count($tracks[$ti]['candidates'])) {
                    unset($tracks[$ti]);
                }
            }

            if (!$allocatedThisPass) {
                break;
            }
        }

        return $totalAllocated;
    }

    /**
     * Create a single transaction allocating to one beneficiary, applying the cron's
     * constraints (donor school/uni choice, payment-type match, beneficiary remaining for
     * the period, and the per-person yearly cap). The beneficiary is paid through the first
     * of its payment methods whose type still has budget in $typeBudgets (type => available RSD).
     *
     * @param array<int,int> $typeBudgets available RSD keyed by payment type
     * @param int $minSlice a type must have at least this much budget to be used (the 10k rule for large donors)
     * @return array{type: int|null, amount: int} the type used and RSD allocated, or amount 0 when skipped
     */
    private function allocateToBeneficiary(
        Donor $donor, Project $project, Period $period, Beneficiary $beneficiary, array $typeBudgets,
        int $minSlice = self::MIN_TRANSACTION_DONATION_AMOUNT
    ): array {
        $skip = ['type' => null, 'amount' => 0];

        // Donor's school/uni preference (MSP only). School types 9 and 17 are universities.
        if ($project->code === 'MSP') {
            $typeId = $beneficiary->school->type->id ?? null;
            $isUni = in_array($typeId, [9, 17], true);
            if ($donor->wantsToDonateTo === Donor::DONATE_TO_SCHOOL && $isUni) {
                return $skip;
            }
            if ($donor->wantsToDonateTo === Donor::DONATE_TO_UNI && !$isUni) {
                return $skip;
            }
        }

        // Match a beneficiary payment method to a donor payment type that still has budget.
        $beneficiaryPM = null;
        $paymentType = null;
        foreach ($beneficiary->paymentMethods as $pm) {
            if (($typeBudgets[$pm->type] ?? 0) >= $minSlice) {
                $beneficiaryPM = $pm;
                $paymentType = $pm->type;
                break;
            }
        }
        if (!$beneficiaryPM) {
            return $skip;
        }

        $receivedSoFar = $this->repo->getSumAmountForBeneficiary($beneficiary, $project, $period);
        $beneficiaryRemaining = $beneficiary->getAmountForPeriod($period) - $receivedSoFar;
        if ($beneficiaryRemaining <= self::MIN_TRANSACTION_DONATION_AMOUNT) {
            return $skip;
        }

        $perPersonRemaining = $this->repo->getRemainingPerPersonLimit($donor, $beneficiary);
        if ($perPersonRemaining <= 0) {
            return $skip;
        }

        $transactionAmount = min($typeBudgets[$paymentType], $beneficiaryRemaining, $perPersonRemaining);

        $accountNumber = null;
        $instructions = null;
        $amountEur = 0;
        if ($paymentType === PaymentMethod::TYPE_BANK_TRANSFER) {
            $accountNumber = $beneficiaryPM->accountNumber;
        } else {
            $amountEur = TransactionEntity::rsdToEur($transactionAmount);
            $instructions = $beneficiaryPM->wireInstructions;
        }

        try {
            $this->create([
                'donor' => $donor->id,
                'project' => $project->id,
                'amount' => $transactionAmount,
                'amountEur' => $amountEur,
                'period' => $period->id,
                'comment' => '',
                'status' => TransactionEntity::STATUS_NEW,
                'beneficiary' => $beneficiary->id,
                'paymentType' => $paymentType,
                'accountNumber' => $accountNumber,
                'instructions' => $instructions,
                // Allocator-generated, not a form submission — CSRF was already validated
                // upstream (cron has no request; the donor's on-demand form is CSRF-checked
                // in DonorDonationData). Without this the Transaction validator rejects it.
                'skipCsrf' => true,
                // The allocator already matched donor↔beneficiary by the donor's chosen
                // payment types; skip the validator's persisted-payment-method check, which
                // doesn't apply to on-demand donations (the choice is on the form).
                'skipDonorPaymentCheck' => true,
            ]);
        } catch (\Skeletor\Core\Validator\ValidatorException $e) {
            // One beneficiary failing transaction validation must not abort the whole run.
            // The real reason lives on the Transaction validator (parseErrors), not the
            // donation validator the caller reads — so log it here with full context.
            $this->logger->warning(sprintf(
                'Skipped transaction (donor %d, beneficiary %d, project %d, period %d, type %d): %s',
                $donor->id, $beneficiary->id, $project->id, $period->id, $paymentType,
                json_encode($this->parseErrors())
            ));
            return $skip;
        }

        return ['type' => $paymentType, 'amount' => $transactionAmount];
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Transaction\Entity\Transaction $transaction */
        foreach ($entities as $transaction) {
            // donor/beneficiary may be null after GDPR redaction — show a placeholder.
            $beneficiaryName = $transaction->beneficiary?->name ?? 'N/A';
            if ($transaction->beneficiary?->school) {
                $beneficiaryName .= '<br />' . $transaction->beneficiary->school->name
                    . '<br />' . $transaction->beneficiary->school->city->name;
            }
            $instructions = $transaction->instructions ?? $transaction->accountNumber;
            if (!$instructions) {
                $instructions = PaymentMethod::getHrType($transaction->paymentType);
            }

            $itemData = [
                'id' => $transaction->getId(),
                'accountNumber' =>  [
                    'value' => $instructions,
                    'editColumn' => true,
                ],
                // @TODO find a clean way to translate
                'status' => \Solidarity\Transaction\Entity\Transaction::getHrStatuses()[$transaction->status],
                'amountEur' => number_format($transaction->amountEur, 0),
                'amount' => number_format($transaction->amount, 0),
                'email' => $transaction->donor
                    ? $transaction->donor->firstName . ' ' . $transaction->donor->lastName . '<br />' . $transaction->donor->email
                    : 'N/A',
                'name' => $beneficiaryName,
                'project' => $transaction->project->code,
                'createdAt' => $transaction->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $transaction->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        // @TODO add filter per school, search per donor/educator details
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Donator'],
            ['name' => 'name', 'label' => 'Oštećeni'],
            ['name' => 'accountNumber', 'label' => 'Br računa'],
            ['name' => 'amount', 'label' => 'Iznos <br /> (RSD)'],
            ['name' => 'amountEur', 'label' => 'Iznos <br /> (EUR)'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Transaction\Entity\Transaction::getHrStatuses()],
            ['name' => 'project', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'createdAt', 'label' => 'Datum'],
        ];

        return $columnDefinitions;
    }

    public function compileXlsxTransactionList($transactions, $school)
    {
        $spreadsheet = new Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->getSpreadsheet()->getProperties()
            ->setCreator("MS")
            ->setLastModifiedBy("MS");
        $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
        $sheet = $writer->getSpreadsheet()->getActiveSheet();

        $sheet->getCell('A1')->setValue('#');
        $sheet->getCell('B1')->setValue('Ime oštećenog');
        $sheet->getCell('C1')->setValue('Iznos');
        $sheet->getCell('D1')->setValue('Broj računa');
        $sheet->getCell('E1')->setValue('Izaberi status');
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->getColumnDimension('E')->setAutoSize(false);
        $sheet->getColumnDimension('E')->setWidth(20);
        $row = 2;
        foreach ($transactions as $transaction) {
            $row++;
            $sheet->getCell('A' . $row)->setValue($transaction->id);
            $sheet->getCell('B' . $row)->setValue($transaction->name);
            $sheet->getCell('C' . $row)->setValue($transaction->amount);
            $sheet->getCell('D' . $row)->setValue($transaction->accountNumber .' ');

            $sheet->getCell('E'.$row)->getDataValidation()
                ->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setPrompt('Izaberi status')
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setFormula1('"Plaćeno,Neplaćeno"');
            $sheet->getCell('E'.$row)->getStyle()
                ->getBorders()
                ->getOutline()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK));
        }
        $filePath = DATA_PATH . sprintf('/lists/%s.xlsx', str_replace(' ', '', $school));
        $writer->save($filePath);

        return $filePath;
    }
}