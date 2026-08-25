<?php


namespace Solidarity\ContentEditor\Contracts;
interface ContentEditorFilterInterface
{
    public function filter(array $data): array;
}