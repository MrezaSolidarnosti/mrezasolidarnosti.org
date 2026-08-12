<?php

namespace Solidarity\Backend\Controller;

use GuzzleHttp\Psr7\Response;
use Laminas\Config\Config;
use Laminas\Session\SessionManager as Session;
use League\Plates\Engine;
use Skeletor\Core\Controller\AjaxCrudController;
use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\ContentEditor\Exceptions\BlockFilterNotFoundException;
use Solidarity\Post\Service\Post;
use Tamtamchik\SimpleFlash\Flash;

class PostController extends AjaxCrudController
{
    const string TITLE_VIEW = "View posts";
    const string TITLE_CREATE = "Create new post";
    const string TITLE_UPDATE = "Edit post: ";
    const string TITLE_UPDATE_SUCCESS = "Post updated successfully.";
    const string TITLE_CREATE_SUCCESS = "Post created successfully.";
    const string TITLE_DELETE_SUCCESS = "Post deleted successfully.";
    const string PATH = 'Post';

    public function __construct(
        Post $service, Session $session, Config $config, Flash $flash, Engine $template,
    ) {
        parent::__construct($service, $session, $config, $flash, $template);
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
        } catch (\Exception $e) {
//            $this->logger->error('Create failed: ' . $e->getMessage(), ['exception' => $e]);
            $generalErrors[] = ['message' => $this->translate('An unexpected error occurred. Please try again.')];
        }
        $entityData = [];
        if($entity) {
            $entityData = $this->service->getEntityData($entity->id);
        }
        $this->getResponse()->getBody()->write(json_encode([
            'errors' => $errors,
            'message' => $message,
            'generalErrors' => $generalErrors,
            'status' => $status,
            'data' =>  $entityData,
            'token' =>  \Volnix\CSRF\CSRF::getHiddenInputString()
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
            'data' => $this->service->getEntityData($data['id']),
            'token' => \Volnix\CSRF\CSRF::getHiddenInputString()
        ]));
        $this->getResponse()->getBody()->rewind();

        return $this->getResponse()->withHeader('Content-Type', 'application/json');
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

            $status = ['status' => $model->status];
            if($model->publishAt) {
                $status['schedule'] = $model->publishAt->format('Y-m-d H:i:s');
            }
            $initialContent = [
                'title' => $model->title,
                'slug' => $model->slug,
                'blocks' => $model->blockData,
                'featuredImage' => ['id' => $model->featuredImage?->id, 'src' => $model->featuredImage?->filename],
                'status' => $status,
                'excerpt' => $model->shortDescription,
                'seo' => [
                    'title' => $model->seoTitle,
                    'description' => $model->seoDescription,
                    'image' => ['id' => $model->seoImage?->id, 'src' => $model->seoImage?->filename]
                ]
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
}