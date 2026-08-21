<?php

namespace Solidarity\Post\Validator;

use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorInterface;
use Solidarity\Post\Repository\PostRepository;
use Skeletor\Core\Security\Csrf;

class Post implements ValidatorInterface
{
    private array $messages = [];

    public function __construct(private Csrf $csrf, protected PostRepository $postRepository)
    {
    }

    public function isValid(array $data): bool
    {
        $valid = true;
        if (!$this->csrf->validate($data)) {
            throw new InvalidFormTokenException();
        }

        if(trim($data['title']) === '') {
            $this->messages['title'][] = 'Title is required.';
            $valid = false;
        }
        if(trim(strlen($data['title']) < 5)) {
            $this->messages['title'][] = 'Title must be at least 5 characters.';
            $valid = false;
        }
        if(trim($data['slug']) === '') {
            $this->messages['slug'][] = 'Slug is required.';
            $valid = false;
        }
        if($data['status'] === '-1') {
            $this->messages['status'][] = 'Status is required.';
            $valid = false;
        }
        if($data['slug']) {
            $post = $this->postRepository->fetchAll(['slug' => $data['slug']]);
            if(isset($post[0])) {
                if(isset($data['id']) && trim($data['id'] !== '')) {
                    if($post[0]->id !== $data['id']) {
                        $this->messages['slug'][] = 'Slug already exists.';
                        $valid = false;
                    }
                } else {
                    $this->messages['slug'][] = 'Slug already exists.';
                    $valid = false;
                }
            }
        }
        if(!isset($data['seoTitle']) || trim($data['seoTitle']) === '') {
            $this->messages['seoTitle'][] = 'SEO Title is required.';
            $valid = false;
        }
        if(!isset($data['seoDescription']) || trim($data['seoDescription']) === '') {
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