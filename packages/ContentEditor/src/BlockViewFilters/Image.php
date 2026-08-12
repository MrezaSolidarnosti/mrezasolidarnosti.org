<?php

namespace Solidarity\ContentEditor\BlockViewFilters;

use Skeletor\Image\Service\Image as ImageService;
use Solidarity\ContentEditor\Contracts\BlockViewFilterInterface;

class Image implements BlockViewFilterInterface
{
    public function __construct(private ImageService $imageService)
    {

    }

    public function filter(array $data): array
    {
        if(empty($data['mediaId'])) {
            return $data;
        }
        try {
            $image = $this->imageService->getEntityData($data['mediaId']);
        } catch (\Throwable $e) {
            return $data;
        }
        $data['alt'] = $image['alt'] ?? '';
        $data['author'] = $image['author'] ?? '';

        return $data;
    }
}
