<?php

namespace Solidarity\Page\Service;

use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\TableView\Service\TableView;
use Skeletor\Tenant\Repository\TenantRepositoryInterface as TenantRepo;
use Skeletor\User\Service\Session;
use Solidarity\Page\Repository\PageRepository;

class Page extends TableView
{
    public function __construct(
        PageRepository $repository,
        Session $userSession,
        Logger $logger,
        \Solidarity\Page\Filter\Page $filter,
        \Skeletor\Core\Activity\Service\Activity $activity,
        ?TenantRepo $tenant = null
    ) {
        parent::__construct($repository, $userSession, $logger, $filter, $tenant, activity: $activity);
    }

    public function prepareEntities($entities)
    {
        $items = [];
        foreach ($entities as $page) {
            $imgHtml = '';
            if ($page->featuredImage) {
                $image = $page->featuredImage->filename;
                $imageUrl = "/images" . $image;
                $imgHtml = '<img width="90px" src="'.$imageUrl.'" alt="category image">';
            }
            $itemData = [
                'id' => $page->id,
                'title' =>  [
                    'value' => $page->title,
                    'editColumn' => true,
                ],
                'slug' => $page->slug,
                'languageCode' => $page->languageCode,
                'image' => $imgHtml,
                'status' => \Skeletor\Page\Entity\Page::getHrStatus($page->status),
                'createdAt' => $page->createdAt->format('d.m.Y'),
                'updatedAt' => $page->updatedAt->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $page->id,
                'canCreateTranslationPage' => $this->canCreateTranslationOfPage($page)
            ];
        }
        return $items;
    }


    public function compileTableColumns(): array
    {
        return [
            ['name' => 'title', 'label' => 'Name'],
            ['name' => 'slug', 'label' => 'Slug'],
            ['name' => 'languageCode', 'label' => 'Lang', 'filterData' => ['sr' => 'sr', 'en' => 'en']],
            ['name' => 'status', 'label' => 'Status'],
            ['name' => 'updatedAt', 'label' => 'Updated at'],
            ['name' => 'createdAt', 'label' => 'Created at'],
        ];
    }

    public function canCreateTranslationOfPage(\Solidarity\Page\Entity\Page $page): bool
    {
        if($page->languageCode !== 'sr') {
            return false;
        }
        $translationExists = $this->getEntities(['languageCode' => 'en', 'translationGroupId' => $page->id]);
        if(count($translationExists) > 0) {
            return false;
        }
        return true;
    }

    public function createTranslation(int $pageId)
    {
        $this->repo->createTranslation($pageId);
    }
}