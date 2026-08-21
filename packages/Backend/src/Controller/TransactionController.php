<?php
namespace Solidarity\Backend\Controller;

use GuzzleHttp\Psr7\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\Donor\Service\Donor;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Period\Service\Period;
use Solidarity\Transaction\Service\Project;
use Solidarity\Transaction\Service\Transaction;
use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Laminas\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\User\Entity\User;
use Tamtamchik\SimpleFlash\Flash;
use Turanjanin\SerbianTransliterator\Transliterator;

class TransactionController extends AjaxCrudController
{
    const TITLE_VIEW = "View transactions";
    const TITLE_CREATE = "Create new transaction";
    const TITLE_UPDATE = "Edit transaction: ";
    const TITLE_UPDATE_SUCCESS = "Transaction updated successfully.";
    const TITLE_CREATE_SUCCESS = "Transaction created successfully.";
    const TITLE_DELETE_SUCCESS = "Transaction deleted successfully.";
    const PATH = 'Transaction';

    const MAX_DONATIONS = 5;
    const MAX_DONATION_AMOUNT = 30000;

    /**
     * @param Transaction $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        Transaction    $service, Session $session, Config $config, Flash $flash, Engine $template,
        private Donor  $donor, private Project $project, private Mailer $mailer, private Period $period,
        private Delegate $delegate, private BeneficiaryService $beneficiaryService
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
        if ($this->getSession()->getStorage()->offsetGet('loggedInRole') !== User::ROLE_ADMIN) {
            $this->tableViewConfig['createButton'] = false;
        }
    }

    public function updateStatusBulk(): Response
    {
        $returnStatus = 200;
        $success = false;
        try {
            $status = (int) ($this->getRequest()->getQueryParams()['status'] ?? 0);
            $data = json_decode($this->getRequest()->getBody(), true);
            if(!isset($data['ids'])) {
                throw new \Exception('No ids provided.');
            }
            $validStatuses = [
                \Solidarity\Transaction\Entity\Transaction::STATUS_CANCELLED,
                \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED,
//            \Solidarity\Transaction\Entity\Transaction::STATUS_REJECTED,
            ];
            if (!in_array($status, $validStatuses, true)) {
                $response = [
                    'success' => false,
                    'message' => 'Status transakcije nije validan.',
                ];
                $this->getResponse()->getBody()->write(json_encode($response));
                $this->getResponse()->getBody()->rewind();
                return $this->getResponse()->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $failed = [];
            $message = '';
            foreach($data['ids'] as $id) {
                try {
                    $this->service->updateStatus($id, $status);
                    if ($status === \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED) {
                        $message = "Transakcija su potvrđene.";
                    } elseif ($status === \Solidarity\Transaction\Entity\Transaction::STATUS_CANCELLED) {
                        $message = "Transakcija su otkazane.";
                    }
                    $success = true;
                } catch (\Exception $e) {
                    $failed[$id] = $e->getMessage();
                    $success = false;
                    $message = $e->getMessage();
                    $returnStatus = 500;
                }
            }
        } catch (\Exception $e) {
            // todo might not need to translate all exception :)
            $message = $this->translate($e->getMessage());
            $success = false;
        }
        $this->getResponse()->getBody()->write(json_encode([
            'message' => $message,
            'success' => $success,
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json')->withStatus($returnStatus);
    }

    public function uploadTransactionListForm()
    {
        return $this->respond('uploadTransactionList', []);
    }

    /**
     * The uploaded name is chosen by whoever sends the file and ends up in a filesystem
     * path, so it is reduced to a bare filename first: basename() drops any directory part
     * and the character filter drops what is left of a traversal, so nothing outside
     * DATA_PATH can be written. Dots are kept (the extension picks the reader) but not
     * leading ones, which would otherwise write a hidden file.
     */
    private static function safeUploadName(?string $clientFilename): string
    {
        $name = ltrim(preg_replace('/[^A-Za-z0-9._-]+/', '', basename((string) $clientFilename)) ?? '', '.');

        return $name !== '' ? $name : 'upload.xlsx';
    }

    public function uploadTransactionList()
    {
        /* @var UploadedFile $uploadedFile */
        $uploadedFile = $this->getRequest()->getUploadedFiles()['file'];
        $fileName = self::safeUploadName($uploadedFile->getClientFilename());
        $uploadedFile->moveTo(DATA_PATH . '/' . $fileName);
        $parts = explode('.', $fileName);
        if (strtolower($parts[count($parts)-1]) === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        $reader->setReadDataOnly(true);
        $excel = $reader->load(DATA_PATH . '/' . $fileName);
        $failedData = [];
        foreach ($excel->getSheet($excel->getFirstSheetIndex())->toArray() as $key => $data) {
            if ($key < 2) {
                continue;
            }
            if (!$data[0]) {
                $this->getFlash()->error('<p>Missing id</p>');
                $failedData[] = $data;
                continue;
            }
            $transaction = $this->service->getById($data[0]);
            if (!$transaction) {
                $this->getFlash()->error('<p>Transaction not found in database. id:' . $data[0]. '</p>');
                $failedData[] = $data;
                continue;
            }
            if ($transaction->amount != $data[2]) {
                $this->getFlash()->error('<p>Transaction amount mismatch. id:' . $data[0] . '</p>');
                $failedData[] = $data;
                continue;
            }
            switch ($data[4]) {
                case 'Plaćeno':
                $status = \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED;
                    break;
                case 'Neplaćeno':
                default:
                $status = \Solidarity\Transaction\Entity\Transaction::STATUS_CANCELLED;
                break;
            }
            try {
                $this->service->updateField('status', $status, $data[0]);
            } catch (\Exception $e) {
                $this->getFlash()->error($e->getMessage());
                $failedData[] = $data;
            }
        }
        if (count($failedData)) {
            foreach ($failedData as $item) {
//                $this->getFlash()->error('Invalid/changed data found for transaction id:' . $item[0]);
            }
        }
        $this->getFlash()->success('Transactions updated.');
        return $this->redirect('/transaction/uploadTransactionListForm/');
    }

    // todo consider if status is 3 or 4, to disable status change completely
    public function updateStatus(): Response
    {
        $id = (int) $this->getRequest()->getAttribute('id');
        $data = $this->getRequest()->getQueryParams();
        $status = (int) ($data['status'] ?? 0);

        $validStatuses = [
            \Solidarity\Transaction\Entity\Transaction::STATUS_CANCELLED,
            \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED,
//            \Solidarity\Transaction\Entity\Transaction::STATUS_REJECTED,
        ];
        if (!in_array($status, $validStatuses, true)) {
            $this->getResponse()->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Status transakcije nije validan.',
            ]));
            $this->getResponse()->getBody()->rewind();
            return $this->getResponse()->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($status === \Solidarity\Transaction\Entity\Transaction::STATUS_CONFIRMED) {
            $message = "Transakcija je potvrđena.";
        } elseif ($status === \Solidarity\Transaction\Entity\Transaction::STATUS_CANCELLED) {
            $message = "Transakcija je otkazana.";
        }

        try {
            $this->service->updateField('status', $status, $id);
            $this->getResponse()->getBody()->write(json_encode([
                'success' => true,
                'message' => $message,
            ]));
        } catch (\Exception $e) {
            $this->getResponse()->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
            $this->getResponse()->getBody()->rewind();
            return $this->getResponse()->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $this->getResponse()->getBody()->rewind();
        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

    public function form(): Response
    {
        $periods = $this->period->getEntities(['active' => true]);

        if ($this->getSession()->getStorage()->offsetGet('loggedInEntityType') === 'delegate') {
            $delegate = $this->delegate->getById($this->getSession()->getStorage()->offsetGet('loggedIn'));
            $assignedProjects = [];
            foreach ($delegate->projects as $project) {
                $assignedProjects[$project->id] = $project->code . ' - ' . $project->name;
            }
            $assignedPeriods = [];
            foreach ($periods as $period) {
                if (array_key_exists($period->project->id, $assignedProjects)) {
                    $assignedPeriods[$period->id] = $period->getLabel();
                }
            }
        } else {
            $assignedProjects = $this->project->getFilterData();
            $assignedPeriods = [];
            foreach ($periods as $period) {
                $assignedPeriods[$period->id] = $period->getLabel();
            }
        }

        $periodProjectMap = [];
        foreach ($periods as $period) {
            $periodProjectMap[$period->id] = $period->project->id;
        }

        // Include the edited transaction's own period even if it's no longer active —
        // transactions are created for `processing` periods, which needn't stay `active`.
        // Without this the Period dropdown can't preselect it and shows "---".
        $id = $this->getRequest()->getAttribute('id');
        if ($id) {
            $transaction = $this->service->getById($id);
            if ($transaction && $transaction->period && !array_key_exists($transaction->period->id, $assignedPeriods)) {
                $assignedPeriods[$transaction->period->id] = $transaction->period->getLabel();
                $periodProjectMap[$transaction->period->id] = $transaction->period->project->id;
            }
        }

        $this->formData['projects'] = $assignedProjects;
        $this->formData['periods'] = $assignedPeriods;
        $this->formData['periodProjectMap'] = $periodProjectMap;

        return parent::form();
    }

    public function getPaymentMethodPreview(): Response
    {
        $params = $this->getRequest()->getQueryParams();
        $donorId = (int) ($params['donorId'] ?? 0);
        $beneficiaryId = (int) ($params['beneficiaryId'] ?? 0);
        $projectId = (int) ($params['projectId'] ?? 0);
        $periodId = (int) ($params['periodId'] ?? 0);

        try {
            $donor = $this->donor->getById($donorId);
            $beneficiary = $this->beneficiaryService->getById($beneficiaryId);

            if (!$donor || !$beneficiary) {
                throw new \Exception('Donor or beneficiary not found.');
            }

            // Match the payment type against the donor's pledge for the selected project
            // (falls back to any pledge when no project is chosen yet) so the preview shows
            // the same type that will actually be saved.
            $project = $projectId ? $this->project->getById($projectId) : null;
            $result = \Solidarity\Transaction\Factory\TransactionFactory::matchPaymentType($donor, $beneficiary, $project);
            $result['paymentTypeLabel'] = \Solidarity\Beneficiary\Entity\PaymentMethod::getHrType($result['paymentType']);

            if ($project && $periodId) {
                $periodEntity = $this->period->getById($periodId);

                if ($periodEntity) {
                    $paymentType = $result['paymentType'];

                    // Donor leftover: pledged (in RSD) minus already donated for this payment type + project
                    $donorPM = null;
                    foreach ($donor->getPaymentMethodsForProject($project) as $pm) {
                        if ($pm->type === $paymentType) {
                            $donorPM = $pm;
                            break;
                        }
                    }
                    if (!$donorPM) {
                        // Unreachable while matchPaymentType() picks $paymentType out of this
                        // same project-filtered list. It used to fall through to a leftover of
                        // 0, which presented a broken invariant as a real "nothing left to
                        // give"; failing loudly is better than a wrong number an admin trusts.
                        throw new \Exception('Donor has no pledge of the matched payment type for this project.');
                    }
                    $pledgedRsd = $donorPM->type === \Solidarity\Beneficiary\Entity\PaymentMethod::TYPE_BANK_TRANSFER
                        ? $donorPM->amount
                        : \Solidarity\Transaction\Entity\Transaction::eurToRsd($donorPM->amount);
                    $donatedSoFar = $this->service->getPaidSumAmountForDonorPerProject($donor, $project, $paymentType);
                    $donorLeftover = max(0, $pledgedRsd - $donatedSoFar);

                    // Beneficiary leftover: period allocation minus already received
                    $beneficiaryTotal = $beneficiary->getAmountForPeriod($periodEntity);
                    $beneficiaryReceived = $this->service->getSumAmountForBeneficiary($beneficiary, $project, $periodEntity);
                    $beneficiaryLeftover = max(0, $beneficiaryTotal - $beneficiaryReceived);

                    // Per-person limit remaining
                    $perPersonLeftover = $this->service->getRemainingPerPersonLimit($donor, $beneficiary);

                    $result['donorLeftover'] = $donorLeftover;
                    $result['beneficiaryLeftover'] = $beneficiaryLeftover;
                    $result['perPersonLeftover'] = $perPersonLeftover;
                    $result['maxAmount'] = max(0, min($donorLeftover, $beneficiaryLeftover, $perPersonLeftover));
                }
            }

            $this->getResponse()->getBody()->write(json_encode(['success' => true, 'data' => $result]));
        } catch (\Exception $e) {
            $this->getResponse()->getBody()->write(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }

        $this->getResponse()->getBody()->rewind();
        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

    public function getEntityData()
    {
        $this->getResponse()->getBody()->write(json_encode($this->service->getEntityData(
            (int) $this->getRequest()->getAttribute('id'), $this->getRequest()->getQueryParams()['currency']
        )));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

    public function import()
    {
        ini_set('max_execution_time', 3600);
        ini_set('memory_limit', '512M');
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
//        $excel = $reader->load(APP_PATH . '/lista-svih-uplata-runda1-15.xlsx');
        $excel = $reader->load(APP_PATH . '/failed-educators-trx.xlsx');
        $failedData = [];
        $failedDonorsTrx = [];
        $failedEducatorsTrx = [];
        foreach ($excel->getSheet($excel->getFirstSheetIndex())->toArray() as $key => $data) {
            if ($key === 0) {
                continue;
            }
            if (!$data[1]) {
                break;
            }
            $name = Transliterator::toLatin($data[1]);
            $accountNumber = $this->normalizeAccountNumber($data[3]);
//            $educator = $this->educator->getEntities(['name' => $name, 'accountNumber' => $accountNumber]);
            $educator = $this->educator->getEntities(['name' => $name]);
            if (!$educator) {
//                var_dump($data);
                $failedEducatorsTrx[] = $data;
                continue;
            }
            $donor = $this->donor->getEntities(['email' => trim($data[0])]);
            if (!$donor) {
                $failedDonorsTrx[] = $data;
                continue;
            }
            $trx = $this->service->getEntities(['name' => $name, 'amount' => $data[2], 'email' => $data[0]]);
            if (count($trx)) {
                continue;
            }
            $trxData = [
                'amount' => $data[2],
                'name' => $name,
                'status' => \Solidarity\Transaction\Entity\Transaction::STATUS_NEW,
                'email' => $data[0],
                'accountNumber' => $accountNumber,
                'educator' => $educator[0]->id,
                'donor' => $donor[0]->id,
                'comment' => '',
                'round' => 1,
            ];

            try {
                $this->service->create($trxData);
            } catch (\Exception $e) {
                var_dump($data);
                var_dump($e->getMessage());
                var_dump($this->service->parseErrors());
                $failedData[] = $data;
            }
        }

        // failed trx cause donors
        $spreadsheet = new Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->getSpreadsheet()->getProperties()
            ->setCreator("MS")
            ->setLastModifiedBy("MS");
        $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
        $sheet = $writer->getSpreadsheet()->getActiveSheet();

        $sheet->getCell('A1')->setValue('email');
        $sheet->getCell('B1')->setValue('name');
        $sheet->getCell('C1')->setValue('amount');
        $sheet->getCell('D1')->setValue('accountNumber');
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        foreach ($failedDonorsTrx as $row => $item) {
            $sheet->getCell('A' . $row)->setValue($item[0]);
            $sheet->getCell('B' . $row)->setValue($item[1]);
            $sheet->getCell('C' . $row)->setValue($item[2]);
            $sheet->getCell('D' . $row)->setValue($item[3] . ' ');
        }
        $filePath = APP_PATH . '/failed-donors-edu-trx.xlsx';
        $writer->save($filePath);

        // failed trx cause educators
        $spreadsheet = new Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->getSpreadsheet()->getProperties()
            ->setCreator("MS")
            ->setLastModifiedBy("MS");
        $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
        $sheet = $writer->getSpreadsheet()->getActiveSheet();

        $sheet->getCell('A1')->setValue('email');
        $sheet->getCell('B1')->setValue('name');
        $sheet->getCell('C1')->setValue('amount');
        $sheet->getCell('D1')->setValue('accountNumber');
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        foreach ($failedEducatorsTrx as $row => $item) {
            $sheet->getCell('A' . $row)->setValue($item[0]);
            $sheet->getCell('B' . $row)->setValue($item[1]);
            $sheet->getCell('C' . $row)->setValue($item[2]);
            $sheet->getCell('D' . $row)->setValue($item[3] . ' ');
        }
        $filePath = APP_PATH . '/failed-educators-edu-trx.xlsx';
        $writer->save($filePath);

        var_dump($failedData);
        die();
    }

    private function normalizeAccountNumber(string $accountNumber) : string
    {
        $numbersOnly = preg_replace('/[^0-9]/', '', $accountNumber);

        if (strlen($numbersOnly) === 18) {
            return $numbersOnly;
        }

        $parts = [
            substr($numbersOnly, 0, 3),
            substr($numbersOnly, 3, -2),
            substr($numbersOnly, -2),
        ];

        if (strlen($parts[1]) < 13) {
            $parts[1] = str_pad(
                $parts[1],
                13,
                '0',
                STR_PAD_LEFT
            );
        }

        return join('', $parts);
    }
}