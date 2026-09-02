<?php

namespace Solidarity\Backend\Blocks\BlockFilters;

use Skeletor\ContentEditor\Contracts\BlockFilterInterface;

class Registerform implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
