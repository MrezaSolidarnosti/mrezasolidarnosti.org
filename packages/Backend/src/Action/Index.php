<?php

namespace Solidarity\Backend\Action;

use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Config\Config;
use \League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Laminas\Session\ManagerInterface as Session;
use Skeletor\Core\Mapper\NotFoundException;
use Tamtamchik\SimpleFlash\Flash;

class Index extends Html
{
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Flash
     */
    private $flash;

    /**
     * HomeAction constructor.
     * @param Logger $logger
     * @param Config $config
     * @param Engine $template
     */
    public function __construct(
        Logger $logger, Config $config, Engine $template, Session $session, Flash $flash
    ) {
        parent::__construct($logger, $config, $template);
        $this->flash = $flash;
        $this->session = $session;
        $this->setGlobalVariable('loggedIn', $this->session->getStorage()->offsetGet('loggedIn'));
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
        // Unauthenticated visitors land on the user (admin/staff) login by default.
        $url = $this->getConfig()->offsetGet('adminUrl') . '/login/user/magicLinkForm/';

        if ($this->session->getStorage()->offsetGet('loggedIn')) {
            if ($this->session->getStorage()->offsetGet('loggedInEntityType') === 'user') {
                $url = $this->getConfig()->offsetGet('adminUrl') . '/user/view/';
            } else {
                // Must match Delegate::getRedirectPath(), which is where verifyMagicLink() already
                // sends them. /educator/ is retired and has no permission mapping, so landing
                // there denied the delegate and bounced them on with a spurious error.
                $url = $this->getConfig()->offsetGet('adminUrl') . '/beneficiary/view/';
            }
        }

        return $response->withStatus(302)->withHeader('Location', $url);
    }

}