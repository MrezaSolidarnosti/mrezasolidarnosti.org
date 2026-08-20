<?php
namespace Solidarity\Backend\Controller;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\Period\Service\Period;
use Solidarity\Beneficiary\Service\Beneficiary;
use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Service\Project;
use Solidarity\Transaction\Service\Transaction;
use Tamtamchik\SimpleFlash\Flash;

class BeneficiaryController extends AjaxCrudController
{
    const TITLE_VIEW = "Pregledaj ostecene";
    const TITLE_CREATE = "Unesi ostecenu osobu";
    const TITLE_UPDATE = "Izmeni ostecenu osobu: ";
    const TITLE_UPDATE_SUCCESS = "Osteceni izmenjen uspesno.";
    const TITLE_CREATE_SUCCESS = "Osteceni kreiran uspesno.";
    const TITLE_DELETE_SUCCESS = "Osteceni obrisan uspesno.";
    const PATH = 'beneficiary/Beneficiary';

    /**
     * @param Beneficiary $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        Beneficiary $service, Session $session, Config $config, Flash $flash, Engine $template, private School $school,
        private \Redis $redis, private Period $period, private Project $project, private Delegate $delegate,
        private Transaction $transaction, private \Solidarity\Backend\Service\Redaction $redaction
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
    }

    public function delete(): Response
    {
        $id = $this->getRequest()->getAttribute('id');
        // GDPR erasure: strip the account details off their transactions and delete the record.
        $beneficiary = $this->service->getById($id);

        // Nothing found is a failure, not a quiet success — and no success flash either. The
        // flash outlives the response and is what the admin reads on the next page, so one on a
        // stale row is a written record of an erasure that never happened.
        if (!$beneficiary) {
            $this->getResponse()->getBody()->write(json_encode([
                'errors' => [],
                'message' => '',
                'generalErrors' => [['message' => 'Oštećeni nije pronađen.']],
                'status' => false,
            ]));
            $this->getResponse()->getBody()->rewind();

            return $this->getResponse()->withHeader('Content-Type', 'application/json');
        }

        $this->redaction->redactBeneficiary($beneficiary);
        $this->getFlash()->success('Podaci oštećenog su trajno uklonjeni.');

        $this->getResponse()->getBody()->write(json_encode([
            'errors' => [],
            'message' => 'Podaci oštećenog su trajno uklonjeni.',
            'generalErrors' => [],
            'status' => 1,
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

    public function form(): Response
    {
        $id = $this->getRequest()->getAttribute('id');
        $model = null;
        if ($id) {
            $model = $this->service->getById($id);
        }
        $this->formData['schools'] = $this->school->getFilterData();

        $assignedProjects = $this->editableProjects();
        $this->formData['assignedProjects'] = $assignedProjects;
        $this->formData['assignedPeriods'] = $this->editablePeriods($assignedProjects, $model);

        $paymentMethods = [];
        if($model) {
            $paymentMethods = $model->paymentMethods;
        }
        $this->formData['paymentMethods'] = $paymentMethods;
        $this->formData['confirmedAmounts'] = $this->confirmedAmountsFor($model);

        return parent::form();
    }

    /**
     * The projects this user may register a beneficiary against: a delegate is limited to their
     * own, everyone else sees all of them.
     *
     * @return array<int, \Solidarity\Transaction\Entity\Project>
     */
    private function editableProjects(): array
    {
        if ($this->getSession()->getStorage()->offsetGet('loggedInEntityType') !== 'delegate') {
            return $this->project->getEntities();
        }

        $delegate = $this->delegate->getById($this->getSession()->getStorage()->offsetGet('loggedIn'));
        $projects = [];
        foreach ($delegate->projects as $project) {
            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * Active periods of those projects, plus any the beneficiary is already registered for.
     *
     * The list is built from *active* periods, so a registration on a closed round had no
     * <option>: the dropdown fell back to its placeholder and saving dropped the row. It is
     * merged back in for the person who holds it even though it is closed to everyone else —
     * and checked before appending, or the round appears twice and the duplicate-prevention in
     * RegisteredProjects.js starts disabling the wrong one.
     *
     * @param array<int, \Solidarity\Transaction\Entity\Project> $projects
     * @return array<int, \Solidarity\Period\Entity\Period>
     */
    private function editablePeriods(array $projects, ?\Solidarity\Beneficiary\Entity\Beneficiary $beneficiary = null): array
    {
        $periods = [];
        foreach ($this->period->getEntities(['active' => true]) as $period) {
            foreach ($projects as $project) {
                if ($project->id === $period->project->id) {
                    $periods[] = $period;
                }
            }
        }

        if ($beneficiary && $beneficiary->registeredPeriods) {
            $periodIds = array_map(static fn($p) => $p->id, $periods);
            foreach ($beneficiary->registeredPeriods as $rp) {
                if (!in_array($rp->period->id, $periodIds, true)) {
                    $periods[] = $rp->period;
                    $periodIds[] = $rp->period->id;
                }
            }
        }

        return $periods;
    }

    /**
     * Confirmed amount per registered period, keyed "projectId_periodId".
     *
     * @return array<string, int>
     */
    private function confirmedAmountsFor(?\Solidarity\Beneficiary\Entity\Beneficiary $beneficiary = null): array
    {
        $amounts = [];
        if (!$beneficiary || !$beneficiary->registeredPeriods) {
            return $amounts;
        }

        foreach ($beneficiary->registeredPeriods as $rp) {
            $key = $rp->project->getId() . '_' . $rp->period->getId();
            $amounts[$key] = $this->transaction->getSumAmountForBeneficiary(
                $beneficiary, $rp->project, $rp->period
            );
        }

        return $amounts;
    }

    public function import()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        ini_set('max_execution_time', 3600);
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $excel = $reader->load(APP_PATH . '/Osteceni.xlsx');
        $failedData = [];
        $round = $this->round->getActiveRound();
        $data = $excel->getSheet($excel->getFirstSheetIndex())->toArray();

        $new = [];
        $existing = [];

        foreach ($data as $key => $educatorData) {
            if ($key === 0) {
                continue;
            }

            $status = 1;
            switch ($educatorData[8]) {
                case 'Nije verifikovano':
                case 'Novo':
                    $status = \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_NEW;
                    break;
                case 'Poslato':
                    $status = \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_SENT;
                    break;
                case 'Primljeno':
                    $status = \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_RECEIVED;
                    break;
                case 'Za slanje':
                    $status = \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_FOR_SENDING;
                    break;
                case 'AFK duplikat':
                case 'Duplikat':
                    continue(2);
            }
            if (!$educatorData[1]) {
                continue;
            }
            $schoolName = trim(str_replace(['"', "'"], '', $educatorData[1]));
            $cityName = trim(str_replace(['"', "'"], '', $educatorData[5]));
            if ($schoolName === '' && $cityName === '') {
                break; // last row
            }

            $school = $this->school->getByNameAndCity($schoolName, $cityName);
            if (!$school) {
                var_dump($schoolName);
                var_dump($cityName);

                die('school not found');
                $failedData[] = $educatorData;
                continue;
            }

            if (!$educatorData[4]) {
                continue;
            }

            $unixTimestamp = ($educatorData[0] - 25569) * 86400;
            $dateTime = @gmdate("Y-m-d H:i:s", $unixTimestamp);
            $dt = new \DateTime($dateTime);
            $accNumber = str_replace([' ', '-'], '', $educatorData[3]);
            $accNumber = str_replace('O', '0', $accNumber);

            // if found, save amount for round 1
            $educator = $this->service->getEntities(['accountNumber' => $this->normalizeAccountNumber($accNumber)]);
            if (count($educator)) {
//                var_dump($educator[0]->name);
                $educator = $educator[0];
                $amount = intval($educatorData[4]);
                if ($educator->amount === $amount) {
                    $existing[] = $educator;
                }
                $this->service->setRoundAmount($educator, $round, $amount);
                continue;
            }
            $new[] = $educatorData;

//            var_dump($educatorData[2]);

            // skip creating for now
            continue;

            $data = [
                'amount' => $educatorData[4],
                'name' => $educatorData[2],
                'schoolName' => $schoolName,
                'slipLink' => ($educatorData[6] === '') ? '': $educatorData[6],
                'accountNumber' => $accNumber,
                'city' => $cityName,
                'status' => $status,
                'school' => $school->id,
                'createdAt' => $dt
            ];

            try {

                $educator = $this->service->create($data);
                $this->service->setRoundAmount($educator, $round);
            } catch (\Exception $e) {
                var_dump($e->getMessage());
                var_dump($this->service->parseErrors());
                $failedData[] = $educatorData;
            }

        }
        var_dump(count($new));
        var_dump(count($existing));

        die('done, not generating list');


        $spreadsheet = new Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->getSpreadsheet()->getProperties()
            ->setCreator("MS")
            ->setLastModifiedBy("MS");
        $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
        $sheet = $writer->getSpreadsheet()->getActiveSheet();

        $sheet->getCell('A1')->setValue('Timestamp');
        $sheet->getCell('B1')->setValue('skola');
        $sheet->getCell('C1')->setValue('ime');
        $sheet->getCell('D1')->setValue('rachun');
        $sheet->getCell('E1')->setValue('iznos');
        $sheet->getCell('F1')->setValue('grad');
        $sheet->getCell('G1')->setValue('Status');
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        foreach ($failedData as $row => $item) {
//            $sheet->getStyle('A' . $row)
//                ->getNumberFormat();
//                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                // wtflol !? https://github.com/PHPOffice/PhpSpreadsheet/issues/357
//                ->setFormatCode('#');
            $sheet->getCell('A' . $row)->setValue($item[0]);
            $sheet->getCell('B' . $row)->setValue($item[1]);
            $sheet->getCell('C' . $row)->setValue($item[2]);
            $sheet->getCell('D' . $row)->setValue($item[3] . ' ');
            $sheet->getCell('E' . $row)->setValue($item[4]);
            $sheet->getCell('F' . $row)->setValue($item[5]);
            $sheet->getCell('G' . $row)->setValue($item[8]);
        }
        $filePath = APP_PATH . '/failed-acc-no.xlsx';
        $writer->save($filePath);

        var_dump($failedData);
        die('done');
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