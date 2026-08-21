<?php

namespace Solidarity\ContentEditor\Contracts;

interface BlockViewFilterInterface
{
    public function filter(array $data): array;
}