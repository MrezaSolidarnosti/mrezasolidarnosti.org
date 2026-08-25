<?php
namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Security\Authentication\MagicLinkCredentials;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Login\Exception\InvalidCredentials;
use Skeletor\Login\Service\Login;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;

class VerifyEmail extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Delegate\Service\Delegate $delegate,
        protected Navigation $navigationService, protected SocialLinks $socialLinks,
        protected AuthenticatorRegistry $authenticatorRegistry, protected EntityRegistry $entityRegistry,
        protected Login $loginService,
        \Solidarity\Frontend\Service\Session $session,
        private \Solidarity\Frontend\Service\Locale $locale,
    ) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);

    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        try {
            $token = $request->getQueryParams()['token'] ?? null;
            if (!$token) {
                return $this->respond('donor/invalidVerifyEmailToken');
            }
            $credentials = new MagicLinkCredentials($token, 'donor');
            $donor = $this->authenticatorRegistry->authenticate($credentials);   // validates + consumes the token
            $verifyingAfterRegister = $donor->status === \Solidarity\Donor\Entity\Donor::STATUS_NEW;

            if ($donor->status === \Solidarity\Donor\Entity\Donor::STATUS_NEW) {        // first click = email verified
                $donor->status = \Solidarity\Donor\Entity\Donor::STATUS_VERIFIED;
                $this->entityRegistry->getRepository('donor')->updateLoginInfo($donor); // persist
            }

            if($donor->status !== \Solidarity\Donor\Entity\Donor::STATUS_VERIFIED) {
                return $this->redirect($this->locale->localizeUrl('/')); //@TODO redirect to a page displaying a message?
            }
            $this->loginService->login($donor, 'donor');
            if($verifyingAfterRegister) {
                return $this->redirect($this->locale->localizeUrl('/registrovani-ste'));
            }
            return $this->redirect($this->locale->localizeUrl('/instrukcije-za-uplatu'));
        } catch (InvalidCredentials $e) {
            return $this->respond('donor/invalidVerifyEmailToken');
        }
    }
}