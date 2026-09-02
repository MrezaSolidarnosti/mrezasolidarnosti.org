<?php

namespace Solidarity\Backend\Controller;
use GuzzleHttp\Psr7\Response;
use Skeletor\ContentEditor\Exceptions\BlockFilterNotFoundException;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorException;
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

    public function form(): Response
    {
        $id = $this->getRequest()->getAttribute('id');
        $model = null;
        $this->setGlobalVariable('pageTitle', static::TITLE_CREATE);
        $formTitle = static::TITLE_CREATE;
        $initialContent = [];
        if ($id) {
            $model = $this->service->getById($id);
            $title = $model->getId();
            $formEntityTitle = '#' . $model->getId();
            $reflectionClass = new \ReflectionClass($model::class);
            if(static::FORM_TITLE_ENTITY_IDENTIFIER !== NULL &&
                $reflectionClass->hasProperty(static::FORM_TITLE_ENTITY_IDENTIFIER)) {
                $property = static::FORM_TITLE_ENTITY_IDENTIFIER;
                if($model->$property !== null) {
                    $title = $model->$property;
                    $formEntityTitle = $model->$property;
                }
            }
            $formTitle = sprintf('%s %s', static::TITLE_UPDATE, $formEntityTitle);
            $this->setGlobalVariable('pageTitle', static::TITLE_UPDATE . $title);
            $formAction = sprintf('/%s/update/%s/', strtolower(static::PATH), $id);
            $dataAction = 'update';

            // No schedule key: a page has no publishAt, so the status module only ever
            // carries the status itself.
            $initialContent = [
                'title' => $model->title,
                'slug' => $model->slug,
                'blocks' => $model->blockData,
                'featuredImage' => ['id' => $model->featuredImage?->id, 'src' => $model->featuredImage?->filename],
                'status' => ['status' => $model->status],
                'seo' => [
                    'title' => $model->seoTitle,
                    'description' => $model->seoDescription,
                    'image' => ['id' => $model->seoImage?->id, 'src' => $model->seoImage?->filename]
                ],
                'languageCode' => $model->languageCode ?? 'sr',
                'loginProtected' => $model->isLoginProtected ?? false
            ];
        } else {
            $formAction = sprintf('/%s/create/', strtolower(static::PATH));
            $dataAction = 'create';
        }
        $path = sprintf('/%s/', static::PATH);
        if (strlen($this->tableViewConfig['adminPath'])) {
            $path = sprintf('/%s/%s/', $this->tableViewConfig['adminPath'], static::PATH);
        }

        return $this->respond('form', array_merge($this->formData, [
            'model' => $model,
            'path' => $path,
            'formTitle' => $formTitle,
            'formAction' => $formAction,
            'dataAction' => $dataAction,
            'initialContent' => $initialContent
        ]));
    }

    public function create(): Response
    {
        $errors = [];
        $status = false;
        $message = '';
        $entity = $generalErrors = [];
        $data = $this->getRequest()->getParsedBody();
        try {
            $entity = $this->service->create($data);
            $status = true;
            $message = $this->translate(static::TITLE_CREATE_SUCCESS);
        } catch (InvalidFormTokenException $e) {
            $errors[] = ['message' => $this->translate('Access denied. Please refresh the page and try again.')];
        } catch (ValidatorException $e) {
            foreach ($this->service->parseErrors() as $key => $error) {
                $errors[] = ['message' => $this->translate($error['message'])];
            }
        } catch (\Throwable $e) {
//            $this->logger->error('Create failed: ' . $e->getMessage(), ['exception' => $e]);
            $generalErrors[] = ['message' => $this->translate('An unexpected error occurred. Please try again.')];
        }
        $this->getResponse()->getBody()->write(json_encode([
            'errors' => $errors,
            'message' => $message,
            'generalErrors' => $generalErrors,
            'status' => $status,
            'data' =>  $entity ? ['id' => $entity->id, 'slug' => $entity->slug] : [],
            'token' =>  $this->csrf()->getHiddenInputString()
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
    }

    public function update(): Response
    {
        $errors = [];
        $status = false;
        $message = '';
        $entity = $generalErrors = [];
        $data = $this->getRequest()->getParsedBody();
        $token = null;
        try {
            $data['id'] = $this->getRequest()->getAttribute('id');
            $entity = $this->service->update($data);
            $status = true;
            $message = $this->translate(static::TITLE_UPDATE_SUCCESS);
        } catch (InvalidFormTokenException $e) {
            $errors[] = ['message' => $this->translate('Access denied. Please refresh the page and try again.')];
        } catch (ValidatorException $e) {
            foreach ($this->service->parseErrors() as $key => $error) {
                $errors[] = ['message' => $this->translate($error['message'])];
            }
        } catch (BlockFilterNotFoundException $e) {
            $errors[] = ['message' => $this->translate($e->getMessage())];
        }
        catch (\Exception $e) {
//            $this->logger->error('Update failed: ' . $e->getMessage(), ['exception' => $e]);
            $generalErrors[] = ['message' => $this->translate('An unexpected error occurred. Please try again.')];
        }
        $this->getResponse()->getBody()->write(json_encode([
            'errors' => $errors,
            'message' => $message,
            'generalErrors' => $generalErrors,
            'status' => $status,
            'data' => $entity ? ['id' => $entity->id, 'slug' => $entity->slug] : [],
            'token' =>  $this->csrf()->getHiddenInputString()
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
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