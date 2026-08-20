<?php
namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Skeletor\Login\Service\Login;
use Tamtamchik\SimpleFlash\Flash;

/**
 * Logs the current donor out and returns to the homepage.
 *
 * Clears the session via the framework LoginService (same keys written on login)
 * and drops a flash notice. Safe to hit when nobody is logged in — logout() just
 * unsets already-absent keys.
 */
class Logout extends Html
{
    const LOGGED_OUT = 'Uspešno ste se odjavili.';

    public function __construct(
        Logger $logger, Config $config, Engine $template,
        private Login $loginService, private Flash $flash,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $this->loginService->logout();
        $this->flash->success(static::LOGGED_OUT);

        return $this->redirect('/');
    }
}
