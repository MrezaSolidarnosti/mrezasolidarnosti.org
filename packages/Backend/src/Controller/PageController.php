<?php

namespace Solidarity\Backend\Controller;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Solidarity\Page\Service\Page;
use Tamtamchik\SimpleFlash\Flash;
use Skeletor\Core\Controller\AjaxCrudController;


class PageController extends AjaxCrudController
{
    const TITLE_VIEW = "View pages";
    const TITLE_CREATE = "Create page";
    const TITLE_UPDATE = "Edit page: ";
    const TITLE_UPDATE_SUCCESS = "Page updated successfully.";
    const TITLE_CREATE_SUCCESS = "Page created successfully.";
    const TITLE_DELETE_SUCCESS = "Page deleted successfully.";
    const TITLE_TRANSLATION_CREATED = 'Translation page created successfully';

    const TITLE_TRANSLATION_ERROR = 'An error occurred while creating the translation page.';

    const PATH = 'Page';
    const FORM_TITLE_ENTITY_IDENTIFIER = 'title';

    public function __construct(
        Page $pageService, Session $session, Config $config, Flash $flash, Engine $template) {
        parent::__construct($pageService, $session, $config, $flash, $template);
    }

    public function createTranslation()
    {
        $errors = [];
        $status = false;
        $generalError = [];
        $id = (int)$this->getRequest()->getAttribute('id');
        $message = $this->translate(static::TITLE_TRANSLATION_CREATED);
        if(!$id) {
            throw new \InvalidArgumentException('No id provided.');
        }
        try {
            $this->service->createTranslation($id);
            $status = true;
        } catch (\Throwable $e) {
            $message = $this->translate(static::TITLE_TRANSLATION_ERROR);
            $generalError[]['message'] = $this->translate('An unexpected error occurred. Please try again.');
        }

        $this->getResponse()->getBody()->write(json_encode([
            'errors' => $errors,
            'message' => $message,
            'generalErrors' => $generalError,
            'status' => $status,
        ]));
        $this->getResponse()->getBody()->rewind();
        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }
}