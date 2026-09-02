<?php

namespace Solidarity\Backend\Blocks\BlockFilters;

use Skeletor\ContentEditor\Contracts\BlockFilterInterface;

class Instructionsintro implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
