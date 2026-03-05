<?php
namespace Solidarity\Backend\Controller;

use GuzzleHttp\Psr7\Response;
use Laminas\Config\Config;
use Laminas\Session\ManagerInterface;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Skeletor\Core\Controller\Controller;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\Core\Security\Authentication\MagicLinkCredentials;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Login\Exception\InvalidCredentials;
use Skeletor\Login\Filter\ForgotPassword as ForgotPasswordFilter;
use Skeletor\Login\Filter\ResetPassword;
use Skeletor\Login\Repository\ForgotPasswordRepository;
use Skeletor\Login\Service\MagicLinkService;
use Solidarity\Delegate\Filter\Delegate as Filter;
use Tamtamchik\SimpleFlash\Flash;
use \Skeletor\Login\Service\Login;

class DelegateLoginController extends Controller
{
    const LOGGED_OUT = 'You have successfully logged out.';
//    const LOGIN_FORM_PATH = '/admin/login/delegate/loginForm/';
    const FORGOT_PASSWORD_FORM_PATH = '/admin/login/delegate/forgotPasswordForm/';
    const MAGIC_LINK_FORM_PATH = '/admin/login/delegate/magicLinkForm/';

    const LOGIN_ERROR_INVALID = 'Invalid credentials provided.';
    const LOGIN_ERROR_NO_EMAIL = 'Email not found in system.';
    const LOGIN_SUCCESS = 'You have successfully logged in.';

    public $protectedPath = '';

    public function __construct(
        public readonly Login $loginService,
        ManagerInterface $session,
        Config $config,
        Flash $flash,
        Engine $template,
        protected ResetPassword $resetPasswordFilter,
        protected ForgotPasswordRepository $forgotPasswordRepository,
        private MagicLinkService $magicLinkService,
        private AuthenticatorRegistry $authenticatorRegistry
    ) {
        parent::__construct($template, $config, $session, $flash);
    }

    /**
     * Show magic link request form
     */
    public function magicLinkForm(): ResponseInterface
    {
        if ($this->getSession()->getStorage()->offsetGet('loggedIn')) {
            return $this->redirect($this->getSession()->getStorage()->offsetGet('redirectPath'));
        }

        $this->setGlobalVariable('pageTitle', $this->translate('Login'));
        $this->setGlobalVariable('sent', isset($this->getRequest()->getQueryParams()['sent']));

        return $this->respond('magicLinkForm', ['entityType' => 'delegate']);
    }

    /**
     * Request a magic link
     */
    public function requestMagicLink(): ResponseInterface
    {
        try {
            $data = $this->getRequest()->getParsedBody();
            $email = $data['email'] ?? '';
            $entityType = $data['entityType'] ?? 'delegate';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->getFlash()->error('Unesite validnu email adresu');
                return $this->redirect(static::MAGIC_LINK_FORM_PATH);
            }

            $this->magicLinkService->requestMagicLink($email, $entityType);
            $this->getFlash()->success('Link za login je poslat. Proverite mail.');

            return $this->redirect(static::MAGIC_LINK_FORM_PATH . '?sent');
        } catch (NotFoundException $e) {
            $this->getFlash()->error(static::LOGIN_ERROR_NO_EMAIL);
            return $this->redirect(static::MAGIC_LINK_FORM_PATH);
        } catch (\Exception $e) {
            $this->getFlash()->error($e->getMessage());
            return $this->redirect(static::MAGIC_LINK_FORM_PATH);
        }
    }

    /**
     * Verify magic link and log in user
     */
    public function verifyMagicLink(): ResponseInterface
    {
        try {
            $token = $this->getRequest()->getAttribute('token');
            $entityType = $this->getRequest()->getAttribute('entityType') ?? 'delegate';

            if (!$token) {
                throw new InvalidCredentials('Invalid magic link');
            }
            $credentials = new MagicLinkCredentials($token, $entityType);
            $user = $this->authenticatorRegistry->authenticate($credentials);
            $this->loginService->login($user, $entityType);
            $this->getFlash()->success(static::LOGIN_SUCCESS);

            return $this->redirect($user->getRedirectPath());
        } catch (InvalidCredentials $e) {
            $this->getFlash()->error($e->getMessage());
            return $this->redirect(static::MAGIC_LINK_FORM_PATH);
        } catch (\Exception $e) {
            $this->getFlash()->error('An error occurred. Please try again.');
            return $this->redirect(static::MAGIC_LINK_FORM_PATH);
        }
    }
}