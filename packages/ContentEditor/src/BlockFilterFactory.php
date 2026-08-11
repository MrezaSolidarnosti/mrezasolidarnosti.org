<?php

namespace Solidarity\ContentEditor;

use Skeletor\Core\Service\Contracts\CrudServiceInterface;
use Solidarity\ContentEditor\Contracts\BlockFilterFactoryInterface;
use Solidarity\ContentEditor\Contracts\BlockFilterInterface;

class BlockFilterFactory implements BlockFilterFactoryInterface
{
    protected array $blockParsers = [];

    public function __construct(protected CrudServiceInterface $imageService)
    {

    }


    public function createFilter(string $blockName): BlockFilterInterface
    {
        if(!isset($this->blockParsers[$blockName])) {
            throw new \Exception('Block parser not found');
        }
        return $this->blockParsers[$blockName];
    }

    public function registerBlockFilter(string $blockName, BlockFilterInterface $blockFilter): void
    {
        $this->blockParsers[$blockName] = $blockFilter;
    }
}