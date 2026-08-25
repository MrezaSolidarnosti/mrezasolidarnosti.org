<?php
namespace Solidarity\Backend\Controller;

use Skeletor\User\Entity\User;
use Solidarity\Delegate\Service\Delegate;
use Skeletor\Core\Controller\AjaxCrudController;
use GuzzleHttp\Psr7\Response;
use Skeletor\Core\Config\Config;
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
    const TITLE_DELETE_ERROR = "Could not delete delegate";
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

    /**
     * Same as the parent, except it says what actually went wrong.
     *
     * AjaxCrudController::delete() replaces every exception with a flat "Could not delete
     * entity" and its one logger call is commented out — because the logger itself is
     * commented out of that constructor, so there is nowhere for the reason to go. A failed
     * delete therefore leaves no trace in the response or the log, and diagnosing one means
     * reproducing it by hand in SQL.
     *
     * Deletes fail here for one reason in practice: something still references the delegate.
     * Surfacing the driver's message names the table, which is the whole answer.
     */
    public function delete(): Response
    {
        $generalError = [];
        $status = false;
        $message = '';

        try {
            $this->service->delete($this->getRequest()->getAttribute('id'));
            $status = true;
            $message = $this->translate(static::TITLE_DELETE_SUCCESS);
        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a constraint violation arrives as a Doctrine
            // exception but a mapping or type fault arrives as an Error, and the parent's
            // catch lets those escape as a 500 with no message either.
            $generalError[]['message'] = $this->translate(static::TITLE_DELETE_ERROR) . ': ' . $e->getMessage();
        }

        $this->getResponse()->getBody()->write(json_encode([
            'errors' => [],
            'message' => $message,
            'generalErrors' => $generalError,
            'status' => $status,
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

}