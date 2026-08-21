<?php

namespace Solidarity\Frontend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Skeletor\Core\Security\Csrf;

class EmailList extends BaseAction
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        protected \Solidarity\EmailList\Service\EmailList $emailList,
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
                if(trim($data['email']) !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->emailList->subscribe($data['email']);
                } else {
                    $responseData['errors'][] = 'Email is not valid';
                    return $this->returnWithData(false, $responseData, 400);
                }
            } catch(ValidatorException $e) {
                $success = false;
                $responseData['token'] = $this->csrf->getToken();
                foreach($this->emailList->getFilterErrors() as $error){
                    $responseData['errors'][] = $error;
                }
                $statusCode = 400;
            }
            catch (\Exception $e) {
                $success = false;
                $responseData['token'] = $this->csrf->getToken();
                $statusCode = 400;
                $responseData['errors'][] = 'An unexpected error occurred, please refresh the page and try again.';
            }
        }

        return $this->returnWithData($success, $responseData, $statusCode);
    }
}