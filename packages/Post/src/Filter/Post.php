<?php

namespace Solidarity\Post\Filter;

use Laminas\Filter\ToInt;
use Skeletor\Blog\Service\UrlHelper;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\ContentEditor\Contracts\ContentEditorFilterInterface;
use Volnix\CSRF\CSRF;

class Post implements FilterInterface
{
    public function __construct(private \Solidarity\Post\Validator\Post $validator, private ContentEditorFilterInterface $blockFilter) {}

    public function getErrors(): array
    {
        return $this->validator->getMessages();
    }

    public function filter(array $data): array
    {
        if ($data['slug'] && trim($data['slug'] !== '')) {
            $slug = UrlHelper::slugify($data['slug']);
        } else {
            $slug = UrlHelper::slugify($data['title']);
        }
        $statusData = json_decode($data['status'], true);
        $seoData = json_decode($data['seo'], true);
        $filteredData = [
            'id' => (isset($data['id'])) ? (new ToInt())->filter($data['id']) : null,
            'title' => $data['title'],
            'slug' => $slug,
            'shortDescription' => 'filter hardcoded value',
            'status' => (new ToInt())->filter($statusData['status']),
            'blockData' => $this->blockFilter->filter(json_decode($data['blocks'] ?? [], true) ?? []),
            'featuredImageId' => $data['featuredImageId'] ?? '',
            'publishAt' => isset($statusData['schedule']) ? new \DateTime($statusData['schedule']) : null,
            'seoTitle' => $seoData['title'] ?? null,
            'seoDescription' => $seoData['description'] ?? null,
            'seoImageId' => $seoData['image']['id'] ?? null,
            CSRF::TOKEN_NAME => $data[CSRF::TOKEN_NAME],
        ];

        if (!$this->validator->isValid($filteredData)) {
            throw new ValidatorException();
        }

        unset($filteredData[CSRF::TOKEN_NAME]);

        return $filteredData;
    }
}