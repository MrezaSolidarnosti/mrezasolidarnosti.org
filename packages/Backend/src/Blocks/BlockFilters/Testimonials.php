<?php

namespace Solidarity\Backend\Blocks\BlockFilters;

use Skeletor\ContentEditor\Contracts\BlockFilterInterface;

class Testimonials implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
