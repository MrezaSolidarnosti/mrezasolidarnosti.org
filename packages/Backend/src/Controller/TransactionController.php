<?php
namespace Solidarity\Backend\Controller;

use GuzzleHttp\Psr7\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
        private Donor  $donor, private Project $project, private Mailer $mailer, private Period $period
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
//        $this->tableViewConfig['createButton'] = false;
    }

    public function uploadTransactionListForm()
    {
        return $this->respond('uploadTransactionList', []);
    }

    public function uploadTransactionList()
    {
        /* @var UploadedFile $uploadedFile */
        $uploadedFile = $this->getRequest()->getUploadedFiles()['file'];
        $uploadedFile->moveTo(DATA_PATH . '/' . $uploadedFile->getClientFilename());
        $parts = explode('.', basename($uploadedFile->getClientFilename()));
        if ($parts[count($parts)-1] === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        $reader->setReadDataOnly(true);
        $excel = $reader->load(DATA_PATH . '/' . $uploadedFile->getClientFilename());
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
        $this->formData['projects'] = $this->project->getFilterData();
        $this->formData['periods'] = $this->period->getFilterData();
        return parent::form();
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