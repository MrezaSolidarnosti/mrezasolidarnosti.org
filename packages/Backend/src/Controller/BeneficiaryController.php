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
        private Period $period, private Project $project, private Delegate $delegate,
        private Transaction $transaction, private \Solidarity\Backend\Service\Redaction $redaction
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
    }

    public function delete(): Response
    {
        $id = $this->getRequest()->getAttribute('id');
        // GDPR erasure: strip the account details off their transactions and delete the record.
        $beneficiary = $this->service->getById($id);
        if (!$beneficiary) {
            // Reported as a failure, not a confirmation — see DonorController::delete(). The
            // flash matters as much as the payload here: a success flash on a stale row is a
            // written record that data was erased when nothing was.
            $this->getResponse()->getBody()->write(json_encode([
                'errors' => [],
                'message' => '',
                'generalErrors' => [['message' => $this->translate('Oštećeni nije pronađen.')]],
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

        $assignedProjects = $this->editableProjects();

        $this->formData['schools'] = $this->school->getFilterData();
        $this->formData['assignedProjects'] = $assignedProjects;
        $this->formData['assignedPeriods'] = $this->editablePeriods($assignedProjects, $model);
        $this->formData['paymentMethods'] = $model ? $model->paymentMethods : [];
        $this->formData['confirmedAmounts'] = $this->confirmedAmountsFor($model);

        return parent::form();
    }

    /**
     * Projects the person editing may register someone for: their own for a delegate,
     * everything for admins and staff.
     *
     * @return \Solidarity\Transaction\Entity\Project[]
     */
    protected function editableProjects(): array
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
     * Periods offered in the registration rows: the active ones belonging to $projects,
     * plus whatever this beneficiary is already registered for.
     *
     * That merge is the point. The list is built from *active* periods, so a registration on
     * a closed round had no <option>, the dropdown fell back to its empty placeholder, and
     * saving dropped the row. The period stays selectable for the person who holds it even
     * though it is closed to everyone else.
     *
     * @param \Solidarity\Transaction\Entity\Project[] $projects
     * @return \Solidarity\Period\Entity\Period[]
     */
    protected function editablePeriods(array $projects, ?\Solidarity\Beneficiary\Entity\Beneficiary $model): array
    {
        $periods = [];
        foreach ($this->period->getEntities(['active' => true]) as $period) {
            foreach ($projects as $project) {
                if ($project->id === $period->project->id) {
                    $periods[] = $period;
                }
            }
        }

        if (!$model || !$model->registeredPeriods) {
            return $periods;
        }

        $periodIds = array_map(static fn ($p) => $p->id, $periods);
        foreach ($model->registeredPeriods as $rp) {
            if (!in_array($rp->period->id, $periodIds, true)) {
                $periods[] = $rp->period;
                $periodIds[] = $rp->period->id;
            }
        }

        return $periods;
    }

    /**
     * How much has actually reached this beneficiary per registration, keyed
     * `projectId_periodId` — the template reads the same key to render "Potvrđeni iznos".
     *
     * @return array<string, int>
     */
    protected function confirmedAmountsFor(?\Solidarity\Beneficiary\Entity\Beneficiary $model): array
    {
        if (!$model || !$model->registeredPeriods) {
            return [];
        }

        $confirmed = [];
        foreach ($model->registeredPeriods as $rp) {
            $key = $rp->project->getId() . '_' . $rp->period->getId();
            $confirmed[$key] = $this->transaction->getSumAmountForBeneficiary($model, $rp->project, $rp->period);
        }

        return $confirmed;
    }

}