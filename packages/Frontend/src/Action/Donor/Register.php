<?php
namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Action\BaseAction;
use Skeletor\Core\Security\Csrf;

class Register extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        private \Solidarity\Frontend\Service\Locale $locale,
        private \Skeletor\Core\Security\Csrf $csrf) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);

    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        $data = $request->getParsedBody();
        $responseData = [];
        $success = true;
        $statusCode = 200;
        if (!empty($data)) {
            try {
                $data['isActive'] = 1;
                $data['wantsToDonateTo'] = null;
                $data['comment'] = '';
                $data['projects'] = [];
                $data['status'] = \Solidarity\Donor\Entity\Donor::STATUS_NEW;
                $this->donor->create($data);

                $responseData['redirect'] = $this->locale->localizeUrl('/potvrdi-email');
            } catch(ValidatorException $e) {
                $success = false;
                $responseData['token'] = $this->csrf->getToken();
                foreach($this->donor->getFilterErrors() as $error){
                    $responseData['errors'][] = $error;
                }
                $statusCode = 400;
            }
            catch (\Exception $e) {
                $success = false;
                $responseData['token'] = $this->csrf->getToken();
                $statusCode = 400;
                if ($e->getMessage() === "Donor already exists") {
                    $responseData['errors'][] = 'A donor with this email address already exists.';
                }
            }
        }

        return $this->returnWithData($success, $responseData, $statusCode);
    }
}