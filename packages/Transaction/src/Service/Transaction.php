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
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;

class Transaction extends TableView
{
    /**
     * @param TransactionRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        TransactionRepository $repo, Session $user, Logger $logger, TransactionFilter $filter, private ProjectService $project,
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getSumAmountForBeneficiary(Beneficiary $beneficiary, ?Project $project = null, ?Period $period = null) {
        return $this->repo->getSumAmountForBeneficiary($beneficiary, $project, $period);
    }

    public function getPaidSumAmountForDonorPerProject(Donor $donor, Project $project, ?int $paymentType = null)
    {
        return $this->repo->getPaidSumAmountForDonorPerProject($donor, $project, $paymentType);
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

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Transaction\Entity\Transaction $transaction */
        foreach ($entities as $transaction) {
            $beneficiaryName = $transaction->beneficiary->name ?? 'N/A';
            if ($transaction->beneficiary->school) {
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
                'status' => \Solidarity\Transaction\Entity\Transaction::getHrStatuses()[$transaction->status],
//                'accountNumber' => $transaction->accountNumber,
                'amountEur' => number_format($transaction->amountEur, 0),
                'amount' => number_format($transaction->amount, 0),
                'email' => $transaction->donor->firstName .' '. $transaction->donor->lastName .'<br />'. $transaction->donor->email,
                'name' => $beneficiaryName,
                'project' => $transaction->project->code,
                'createdAt' => $transaction->getCreatedAt()->format('d.m.Y'),
//                'updatedAt' => $transaction->getUpdatedAt()->format('d.m.Y'),
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
            ['name' => 'amount', 'label' => 'Iznos (RSD)'],
            ['name' => 'amountEur', 'label' => 'Iznos (EUR)'],
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