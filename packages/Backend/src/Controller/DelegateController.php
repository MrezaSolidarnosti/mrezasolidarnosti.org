<?php
namespace Solidarity\Backend\Controller;

use Skeletor\User\Entity\User;
use Solidarity\Delegate\Service\Delegate;
use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Laminas\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Service\Project;
use Tamtamchik\SimpleFlash\Flash;

class DelegateController extends AjaxCrudController
{
    const TITLE_VIEW = "View delegate";
    const TITLE_CREATE = "Create new delegate";
    const TITLE_UPDATE = "Edit delegate: ";
    const TITLE_UPDATE_SUCCESS = "Delegate updated successfully.";
    const TITLE_CREATE_SUCCESS = "Delegate created successfully.";
    const TITLE_DELETE_SUCCESS = "Delegate deleted successfully.";
    const PATH = 'Delegate';

    /**
     * @param Delegate $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        Delegate       $service, Session $session, Config $config, Flash $flash, Engine $template,
        private School $school, private Project $project
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
        if ($this->getSession()->getStorage()->offsetGet('loggedInRole') !== User::ROLE_ADMIN) {
            $this->tableViewConfig['createButton'] = false;
        }

    }

    public function form(): Response
    {
        $this->formData['projects'] = $this->project->getFilterData();
        $this->formData['schools'] = $this->school->getFilterData();
        return parent::form();
    }

}