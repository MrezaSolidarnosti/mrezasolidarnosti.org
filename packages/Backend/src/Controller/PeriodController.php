<?php
namespace Solidarity\Backend\Controller;

use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\Period\Service\Period;
use Solidarity\Transaction\Service\Project;
use Tamtamchik\SimpleFlash\Flash;

class PeriodController extends AjaxCrudController
{
    const TITLE_VIEW = "View periods";
    const TITLE_CREATE = "Create new period";
    const TITLE_UPDATE = "Edit period: ";
    const TITLE_UPDATE_SUCCESS = "Period updated successfully.";
    const TITLE_CREATE_SUCCESS = "Period created successfully.";
    const TITLE_DELETE_SUCCESS = "Period deleted successfully.";
    const PATH = 'period';

    /**
     * @param Period $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        Period $service, Session $session, Config $config, Flash $flash, Engine $template, private Project $project
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
    }

    public function form(): Response
    {
        $this->formData['projects'] = $this->project->getFilterData();
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

}