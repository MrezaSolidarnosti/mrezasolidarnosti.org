<?php

namespace Solidarity\ContentEditor;

use Solidarity\ContentEditor\Contracts\BlockFilterFactoryInterface;
use Solidarity\ContentEditor\Contracts\ContentEditorFilterInterface;

class Filter implements ContentEditorFilterInterface
{
    public function __construct(protected BlockFilterFactoryInterface $filterFactory)
    {

    }

    public function filter(array $data): array
    {
        $parsedBlockData = [];
        foreach($data as $blockData) {
            if(!isset($blockData['type'])) {
                continue;
            }
            $blockParser = $this->filterFactory->createFilter($blockData['type']);
            $parsedBlockData[] = $blockParser->filter($blockData);
        }
        return $parsedBlockData;
    }
}