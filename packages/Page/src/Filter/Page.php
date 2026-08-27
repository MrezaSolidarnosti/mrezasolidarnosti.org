<?php

namespace Solidarity\Page\Filter;

use Skeletor\Blog\Service\UrlHelper;
use Skeletor\ContentEditor\Contracts\ContentEditorFilterInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Core\Security\Csrf;

class Page implements FilterInterface
{

    public function __construct(protected \Solidarity\Page\Validator\Page $validator, protected ContentEditorFilterInterface $blockFilter)
    {
    }

    public function getErrors()
    {
        return $this->validator->getMessages();
    }

    public function filter(array $postData) : array
    {
        $blockData = $this->decode($postData['blocks'] ?? null);
        $statusData = $this->decode($postData['status'] ?? null);
        $featuredImage = $this->decode($postData['featuredImage'] ?? null);
        $seoData = $this->decode($postData['seo'] ?? null);
        $slug = UrlHelper::slugify($postData['title']);
        if ($postData['slug']) {
            $slug = UrlHelper::slugify($postData['slug']);
        }
        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'title' => $postData['title'],
            'slug' => $slug,
            'status' => (int) ($statusData['status'] ?? 0),
            'featuredImageId' => $featuredImage['id'] ?? '',
            'blockData' => $this->blockFilter->filter($blockData),
            'seoTitle' => $seoData['title'] ?? null,
            'seoDescription' => $seoData['description'] ?? null,
            'seoImageId' => $seoData['image']['id'] ?? '',
            'isLoginProtected' => isset($postData['isLoginProtected']) && $postData['isLoginProtected'] === 'on',
            'languageCode' => $postData['languageCode'] ?? 'sr',
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME],
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?? [];
    }
}