<?php

namespace Solidarity\ContentEditor\Contracts;

interface BlockViewInterface
{
    public function getView(array $data = []): string;

    public function registerViewFilter(string $name, BlockViewFilterInterface $blockViewFilter): void;
}