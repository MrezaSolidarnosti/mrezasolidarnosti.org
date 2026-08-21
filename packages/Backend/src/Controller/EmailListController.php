<?php
namespace Solidarity\Backend\Controller;

use Skeletor\Core\Controller\AjaxCrudController;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\EmailList\Service\EmailList;
use Tamtamchik\SimpleFlash\Flash;

class EmailListController extends AjaxCrudController
{
    const TITLE_VIEW = "View newsletter emails";
    const TITLE_CREATE = "Add newsletter email";
    const TITLE_UPDATE = "Edit newsletter email: ";
    const TITLE_UPDATE_SUCCESS = "Newsletter email updated successfully.";
    const TITLE_CREATE_SUCCESS = "Newsletter email created successfully.";
    const TITLE_DELETE_SUCCESS = "Newsletter email deleted successfully.";
    const PATH = 'EmailList';

    /**
     * @param EmailList $service
     * @param Session $session
     * @param Config $config
     * @param Flash $flash
     * @param Engine $template
     */
    public function __construct(
        EmailList $service, Session $session, Config $config, Flash $flash, Engine $template
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
    }
}
