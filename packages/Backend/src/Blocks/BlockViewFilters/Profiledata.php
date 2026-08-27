<?php

namespace Solidarity\Backend\Blocks\BlockViewFilters;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Frontend\Service\Session;
use Solidarity\Transaction\Service\Transaction;

class Profiledata implements BlockViewFilterInterface
{
    public function __construct(
        protected Session $session,
        protected Transaction $transactionService
    )
    {

    }

    public function filter(array $data): array
    {
        $data['isDonorLoggedIn'] = $this->session->isDonor();
        if ($data['isDonorLoggedIn']) {
            $data['donor'] = $this->session->getUser();
        }
        $data['totalDonated'] = number_format($this->transactionService->getPaidSumAmountForDonor($data['donor']));
        $data['totalDonatedEUR'] = \Solidarity\Transaction\Entity\Transaction::rsdToEur(
            $this->transactionService->getPaidSumAmountForDonor($data['donor'])
        );
        $data['totalTransactions'] = $this->transactionService->getTransactionCountForDonor($data['donor']);

        return $data;
    }
}
