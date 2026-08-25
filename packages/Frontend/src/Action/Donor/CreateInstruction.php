<?php

namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Donor\Service\NoNeedsException;
use Solidarity\Frontend\Action\BaseAction;
use Skeletor\Core\Security\Csrf;

class CreateInstruction extends BaseAction
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
        try {
            $data['donorId'] = $this->session->getId();
            $this->donor->createTransaction($data);
            $responseData['redirect'] = $this->locale->localizeUrl('/instrukcije-za-uplatu?message=created');
        } catch (NoNeedsException $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = $e->getMessage();
        } catch (ValidatorException $e) {
            $success = false;
            foreach ($this->donor->getDonationDataFilterErrors() as $error) {
                $responseData['errors'][] = $error;
            }
            $statusCode = 400;
        } catch (\Exception $e) {
            $success = false;
            $statusCode = 400;
            $responseData['errors'][] = 'An unexpected error occurred, please try again.' . $e->getMessage();
        }
        $responseData['token'] = $this->csrf->getToken();
        return $this->returnWithData($success, $responseData, $statusCode);
    }
}