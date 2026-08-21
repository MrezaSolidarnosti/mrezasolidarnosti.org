<?php

namespace Solidarity\Frontend\Action\Donor;

use Laminas\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;
use Volnix\CSRF\CSRF;

class GetInstructions extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
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
        // Must return, not fall through. This used to set the flag and carry on, so a
        // request that failed the check still got a body full of the donor's instructions —
        // beneficiary names, amounts and reference codes — alongside its 401. Same mistake
        // that was fixed in ConfirmPayment.
        if (!CSRF::validate($data)) {
            return $this->returnWithData(false, [
                'errors' => ['Your session has expired, please refresh the page and try again.'],
                'token' => CSRF::getToken(),
            ], 401);
        }
        try {
            $page = max(1, (int) ($data['page'] ?? 1));
            // ?? never fired here: (int) yields 0, not null, for a missing key — so omitting
            // perPage warned about the undefined index and then paged by zero, which
            // setMaxResults(0) turns into "no instructions at all".
            $perPage = max(1, (int) ($data['perPage'] ?? 10));
            $responseData['instructions'] = $this->donor->getInstructions($this->session->getId(), $page, $perPage);
        } catch (\Exception $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = 'An unexpected error occurred, please try again.';
        }
        $responseData['token'] = CSRF::getToken();
        return $this->returnWithData($success, $responseData, $statusCode);
    }
}