<?php
namespace Solidarity\Frontend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Service\Session;
use Tamtamchik\SimpleFlash\Flash;

class BaseAction extends Html
{

    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        protected Session $session,
    ) {
        parent::__construct($logger, $config, $template);
	    $this->setGlobalVariable( 'url', $this->getConfig()->offsetGet( 'baseUrl' ));
        $this->setGlobalVariable('isHome', false);
        // mainNavigation is set per-locale in public/index.php (frontend i18n).
        $socialLinks = $this->socialLinks->getSocialItems();
        $this->setGlobalVariable('socialLinks', \Solidarity\Frontend\Service\SocialLinks\SocialLinks::getSocialLinks($socialLinks));
        // Logged-in state available to every template (cheap — read straight from session).
        $this->setGlobalVariable('currentUserName', $this->session->getDisplayName());
        $this->setGlobalVariable('isDonorLoggedIn', $this->session->isDonor());
        // Every frontend action passes through here, which makes this the analog of the
        // legacy app's kernel.request listener: one throttled stamp per authenticated donor.
        $this->session->touchVisit();
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $slug = basename(trim($path, '/'));
        $this->setGlobalVariable('slug', $slug);
        if (Flash::some('error')) { // print only errors
            $this->setGlobalVariable('messages', Flash::display());
        }
    }

    /**
     * Guard for donor-only actions. Returns a redirect Response to the login
     * page when no donor is logged in, or null when access is allowed.
     */
    protected function requireDonor(): ?\Psr\Http\Message\ResponseInterface
    {
        if ($this->session->isDonor()) {
            return null;
        }

        return new \GuzzleHttp\Psr7\Response(302, ['Location' => $this->getConfig()->offsetGet('baseUrl') . '/donor/login']);
    }

    public function return404()
    {
        return $this->respond('/index/404');
    }

    protected function setSEO($model): void
    {
        if (property_exists($model, 'seoTitle')) {
            if(property_exists($model, 'slug') && $model->slug === 'homepage') {
                $this->setGlobalVariable('title', $model->seoTitle);
            } else {
                $this->setGlobalVariable('title', $model->seoTitle . ' - ' . $this->getConfig()->siteName);
            }
        } else {
            $this->setGlobalVariable('title', $this->getConfig()->siteName);
        }

        if (property_exists($model, 'seoDescription')) {
            $this->setGlobalVariable('description', $model->seoDescription);
        } else {
            $this->setGlobalVariable('description', $this->getConfig()->siteName);
        }

        if(property_exists($model, 'seoImage') && $model->seoImage) {
            $this->setGlobalVariable('seoImageSrc', $this->getConfig()->offsetGet('baseUrl') . '/images' . $model->seoImage->filename);
            $this->setGlobalVariable('seoImageAlt', $model->seoImage->alt);
        } else {
            $this->setGlobalVariable('seoImageSrc', $this->getConfig()->offsetGet('baseUrl') . FRONT_ASSET_URL . '/images/home.png');
            $this->setGlobalVariable('seoImageAlt', $this->getConfig()->siteName);
        }
    }

    protected function returnWithData(bool $success = true, array $data = [], int $statusCode = 200): \Psr\Http\Message\MessageInterface
    {
        $returnData['success'] = $success;
        $returnData['data'] = $data;
        $this->response->getBody()->write(json_encode($returnData));
        $this->response->getBody()->rewind();
        return $this->response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}