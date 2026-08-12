<?php

namespace Solidarity\ContentEditor\BlockFilters;

use Solidarity\ContentEditor\Contracts\BlockFilterInterface;

class OrderedList implements BlockFilterInterface
{

    public function filter(array $data): array
    {
        return $data;
    }
}
