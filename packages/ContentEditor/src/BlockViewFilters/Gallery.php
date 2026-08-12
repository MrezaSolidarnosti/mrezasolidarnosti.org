<?php

namespace Solidarity\ContentEditor\BlockViewFilters;

use Skeletor\Image\Service\Image as ImageService;
use Solidarity\ContentEditor\Contracts\BlockViewFilterInterface;

class Gallery implements BlockViewFilterInterface
{
    public function __construct(private ImageService $imageService)
    {

    }

    public function filter(array $data): array
    {
        if(empty($data['images']) || !is_array($data['images'])) {
            return $data;
        }
        foreach($data['images'] as $key => $image) {
            if(empty($image['mediaId'])) {
                continue;
            }
            try {
                $entityData = $this->imageService->getEntityData($image['mediaId']);
            } catch (\Throwable $e) {
                continue;
            }
            $data['images'][$key]['alt'] = $entityData['alt'] ?? '';
            $data['images'][$key]['author'] = $entityData['author'] ?? '';
            $data['images'][$key]['label'] = $entityData['label'] ?? '';
        }

        return $data;
    }
}
