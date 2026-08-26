<?php

namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;
use Solidarity\Transaction\Service\Transaction;
use Skeletor\Core\Security\Csrf;

class ConfirmPayment extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        protected Transaction $transaction,
        private \Skeletor\Core\Security\Csrf $csrf) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);

    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    )
    {
        $data = $request->getParsedBody();
        $responseData = [];
        $success = true;
        $statusCode = 200;
        if (!$this->session->isDonor()) {
            return $this->returnWithData(false,
                ['errors' => ['Morate biti ulogovani da bi izvršili ovu akciju.']],
                401
            );
        }
        if(!$this->csrf->validate($data)) {
            // Refused outright. Falling through here confirmed the payment on a rejected token —
            // the error was reported and the transaction was marked paid anyway. The fresh token
            // still goes back so the page can recover from one stale submission.
            $responseData['errors'][] = 'Your session has expired, please refresh the page and try again.';
            $responseData['token'] = $this->csrf->getToken();

            return $this->returnWithData(false, $responseData, 401);
        }
        try {
            $responseData['token'] = $this->csrf->getToken();
            $trx = $this->transaction->getById((int)$data['transactionId']);
            //@TODO move to validator
            if(!$trx) {
                $responseData['errors'][] = 'Transaction not found.';
                return $this->returnWithData(false, $responseData, 404);
            }
            if($trx->donor->id !== $this->session->getUser()->id) {
                $responseData['errors'][] = 'You are not authorized to confirm this payment.';
                return $this->returnWithData(false, $responseData, 403);
            }
            if($trx->status !== \Solidarity\Transaction\Entity\Transaction::STATUS_NEW) {
                $responseData['errors'][] = 'This transaction cannot be confirmed at this time.';
                return $this->returnWithData(false, $responseData, 400);
            }
            if($trx->paymentType === 3) {
                if(empty($data['paymentCode'])) {
                    $responseData['errors'][] = 'Payment code is required for this payment type.';
                    return $this->returnWithData(false, $responseData, 400);
                }
                $this->transaction->updateField('paymentCode', trim($data['paymentCode']), $trx->id);
            }
            $this->transaction->updateField('status', \Solidarity\Transaction\Entity\Transaction::STATUS_WAITING_CONFIRMATION, $trx->id);

            // Paying clears an unpaid-instruction flag on the spot, without waiting for a
            // human. The flag asserts "this donor's instructions keep going unpaid" and the
            // donor has just disproved it; leaving it set would keep them out of allocation
            // until somebody happened to notice. statusChangedAt moves with it — that is where
            // ExpireInstructions restarts counting the streak, so the cleared donor cannot be
            // re-flagged on their old history.
            //
            // Formatted, not a \DateTime: CrudRepository::updateField() interpolates the value
            // straight into DQL, so an object would not survive the trip.
            $donor = $trx->donor;
            $flags = [
                \Solidarity\Donor\Entity\Donor::STATUS_TRY_TO_CONTACT,
                \Solidarity\Donor\Entity\Donor::STATUS_IGNORING_PAYMENTS,
            ];
            if ($donor && in_array($donor->status, $flags, true)) {
                $this->donor->updateField('status', \Solidarity\Donor\Entity\Donor::STATUS_VERIFIED, $donor->id);
                $this->donor->updateField('statusChangedAt', (new \DateTime())->format('Y-m-d H:i:s'), $donor->id);
            }
        } catch (\Exception $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = 'An unexpected error occurred, please try again.';
        }
        return $this->returnWithData($success, $responseData, $statusCode);
    }
}