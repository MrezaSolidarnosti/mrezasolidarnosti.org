<?php
namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;

class Login extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService, protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        private \Solidarity\Frontend\Service\Locale $locale,
    ) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        $data = $request->getParsedBody();
        $responseData = [];
        if (!empty($data)) {
            $email = trim($data['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $responseData['errors'][] = 'Unesite ispravnu email adresu';
                return $this->returnWithData(false, $responseData);
            }
            $this->donor->requestLoginLink($email);
        }
        // Localize the redirect: on `en` this resolves to the translated page slug
        // (e.g. /en/login-link-sent); on the default locale it stays /login-link-poslat.
        $responseData['redirect'] = $this->locale->localizeUrl('/login-link-poslat');

        return $this->returnWithData(true, $responseData);
    }
}
