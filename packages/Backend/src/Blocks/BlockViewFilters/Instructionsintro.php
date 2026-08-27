<?php

namespace Solidarity\Backend\Blocks\BlockViewFilters;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Transaction\Service\Transaction;

class Instructionsintro implements BlockViewFilterInterface
{
    const NAME = 'instructionsintro';

    public function __construct(private Transaction $transaction)
    {

    }

    public function filter(array $data): array
    {
        $buttonText = "";
        if ($this->transaction->hasUnmetNeeds()) {
            $buttonText = $data['buttonText'];
        }
        $data['buttonText'] = $buttonText;

        return $data;
    }
}
