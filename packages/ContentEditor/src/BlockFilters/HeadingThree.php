<?php

namespace Solidarity\ContentEditor\BlockFilters;

use Solidarity\ContentEditor\Contracts\BlockFilterInterface;

class HeadingThree implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
