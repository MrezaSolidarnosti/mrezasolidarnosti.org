<?php

namespace Solidarity\ContentEditor\BlockFilters;

use Solidarity\ContentEditor\Contracts\BlockFilterInterface;

class Divider implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
