<?php

namespace Solidarity\Frontend\Action\Donor;

use Laminas\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;
use Solidarity\Transaction\Service\Transaction;
use Volnix\CSRF\CSRF;

class ConfirmPayment extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        protected Transaction $transaction
    ) {
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
        if(!CSRF::validate($data)) {
            // Must return, not fall through: without this the confirmation below still runs
            // and only the response says 401, which makes the CSRF check decorative.
            return $this->returnWithData(
                false,
                [
                    'errors' => ['Your session has expired, please refresh the page and try again.'],
                    'token' => CSRF::getToken(),
                ],
                401
            );
        }
        try {
            $responseData['token'] = CSRF::getToken();
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
        } catch (\Exception $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = 'An unexpected error occurred, please try again.';
        }
        return $this->returnWithData($success, $responseData, $statusCode);
    }
}