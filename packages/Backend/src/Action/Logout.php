<?php

namespace Solidarity\Backend\Action;

use Psr\Log\LoggerInterface as Logger;
use Laminas\Config\Config;
use \League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Laminas\Session\ManagerInterface as Session;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\Login\Service\Login;
use Tamtamchik\SimpleFlash\Flash;

class Logout extends Html
{
    const LOGGED_OUT = 'You have successfully logged out.';

    /**
     * HomeAction constructor.
     * @param Logger $logger
     * @param Config $config
     * @param Engine $template
     */
    public function __construct(
        Logger $logger, Config $config, Engine $template, private Session $session, private Flash $flash,
        public readonly Login $loginService
    ) {
        parent::__construct($logger, $config, $template);
    }

    /**
     * Parses data for provided merchantId
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request request
     * @param \Psr\Http\Message\ResponseInterface $response response
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        switch ($this->session->getStorage()->offsetGet('loggedInEntityType')) {
//            case 'educator':
//                $url = $this->getConfig()->offsetGet('adminUrl') . '/educator/view/';
//                break;
            case 'delegate':
                $url = $this->getConfig()->offsetGet('adminUrl') . '/login/delegate/magicLinkForm/';
                break;
            default:
                $url = $this->getConfig()->offsetGet('adminUrl') . '/login/user/magicLinkForm/';
        }
        $this->loginService->logout();
        $this->flash->success(static::LOGGED_OUT);

        return $response->withStatus(302)->withHeader('Location', $url);
    }

}