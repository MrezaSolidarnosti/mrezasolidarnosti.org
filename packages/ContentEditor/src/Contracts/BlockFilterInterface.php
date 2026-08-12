<?php

namespace Solidarity\ContentEditor\Contracts;

interface BlockFilterInterface
{
    public function filter(array $data): array;
}