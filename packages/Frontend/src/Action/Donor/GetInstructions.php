<?php

namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;
use Skeletor\Core\Security\Csrf;

class GetInstructions extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
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
            // Refused outright: a rejected token must not reach the donor's instructions at all,
            // so this returns rather than falling through to the read below. The fresh token
            // still goes back, or the page could never recover from one stale submission.
            $responseData['errors'][] = 'Your session has expired, please refresh the page and try again.';
            $responseData['token'] = $this->csrf->getToken();

            return $this->returnWithData(false, $responseData, 401);
        }
        try {
            $page = max(1, (int) ($data['page'] ?? 1));
            // (int) never yields null, so the old `?? 10` was dead and a missing perPage both
            // warned and paged by zero.
            $perPage = (int) ($data['perPage'] ?? 0);
            if ($perPage < 1) {
                $perPage = 10;
            }
            $responseData['instructions'] = $this->donor->getInstructions($this->session->getId(), $page, $perPage);
        } catch (\Exception $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = 'An unexpected error occurred, please try again.';
        }
        $responseData['token'] = $this->csrf->getToken();
        return $this->returnWithData($success, $responseData, $statusCode);
    }
}