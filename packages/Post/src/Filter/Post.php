<?php

namespace Solidarity\Post\Filter;

use Skeletor\Blog\Service\UrlHelper;
use Skeletor\ContentEditor\Contracts\ContentEditorFilterInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Core\Security\Csrf;

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
        $featuredImage = json_decode($data['featuredImage'], true);
        $filteredData = [
            'id' => (isset($data['id'])) ? (int) ($data['id']) : null,
            'title' => $data['title'],
            'slug' => $slug,
            'shortDescription' => $data['excerpt'],
            'status' => (int) ($statusData['status']),
            'blockData' => $this->blockFilter->filter(json_decode($data['blocks'] ?? [], true) ?? []),
            'featuredImageId' => $featuredImage['id'] ?? null,
            'publishAt' => isset($statusData['schedule']) ? new \DateTime($statusData['schedule']) : null,
            'seoTitle' => $seoData['title'] ?? null,
            'seoDescription' => $seoData['description'] ?? null,
            'seoImageId' => $seoData['image']['id'] ?? null,
            Csrf::TOKEN_NAME => $data[Csrf::TOKEN_NAME],
        ];

        if (!$this->validator->isValid($filteredData)) {
            throw new ValidatorException();
        }

        unset($filteredData[Csrf::TOKEN_NAME]);

        return $filteredData;
    }
}