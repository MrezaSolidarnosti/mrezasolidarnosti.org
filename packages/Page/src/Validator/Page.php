<?php

namespace Solidarity\Page\Validator;

use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorInterface;
use Solidarity\Page\Repository\PageRepository;
use Skeletor\Core\Security\Csrf;

class Page implements ValidatorInterface
{
    private $messages = [];


    public function __construct(private Csrf $csrf, protected PageRepository $pageRepository)
    {
    }

    public function isValid(array $data): bool
    {
        if (!$this->csrf->validate($data)) {
            throw new InvalidFormTokenException();
        }
        $valid = true;
        if(trim($data['title']) === '') {
            $this->messages['title'][] = 'Title is required.';
            $valid = false;
        }
        if($data['status'] === '-1') {
            $this->messages['status'][] = 'Status is required.';
            $valid = false;
        }

        if($data['slug']) {
            $page = $this->pageRepository->fetchAll(
                ['slug' => $data['slug'], 'languageCode' => $data['languageCode']]
            );
            if(isset($page[0])) {
                if(isset($data['id']) && trim($data['id'] !== '')) {
                    if($page[0]->id !== $data['id']) {
                        $this->messages['slug'][] = 'Slug already exists.';
                        $valid = false;
                    }
                } else {
                    $this->messages['slug'][] = 'Slug already exists.';
                    $valid = false;
                }
            }
        }

        if(!isset($data['seoTitle'])) {
            $this->messages['seoTitle'][] = 'SEO Title is required.';
            $valid = false;
        }
        if(!isset($data['seoDescription'])) {
            $this->messages['seoDescription'][] = 'SEO Description is required.';
            $valid = false;
        }
        if(!isset($data['seoImageId'])) {
            $this->messages['seoImageId'][] = 'SEO Image is required.';
            $valid = false;
        }

        return $valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}