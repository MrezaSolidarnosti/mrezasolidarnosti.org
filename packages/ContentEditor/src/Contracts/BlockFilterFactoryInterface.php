<?php

namespace Solidarity\ContentEditor\Contracts;


interface BlockFilterFactoryInterface
{
    public function createFilter(string $blockName): BlockFilterInterface;

    public function registerBlockFilter(string $blockName, BlockFilterInterface $blockFilter): void;
}