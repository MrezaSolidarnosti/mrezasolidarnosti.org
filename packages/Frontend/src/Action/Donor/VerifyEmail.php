<?php
namespace Solidarity\Frontend\Action\Donor;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Security\Authentication\MagicLinkCredentials;
use Skeletor\Core\Security\Authenticator\AuthenticatorRegistry;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Core\Login\Exception\InvalidCredentials;
use Skeletor\Core\Login\Repository\MagicLinkTokenRepository;
use Skeletor\Core\Login\Service\Login;
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
        // Read-only peek at the token on GET, so an expired link reports itself before the
        // donor clicks. Consumption still happens through the authenticator on POST.
        private MagicLinkTokenRepository $tokenRepository,
    ) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);

    }

    /**
     * Magic-link login, in two steps.
     *
     * GET only checks the token and renders a confirmation button; the POST spends it. That
     * split exists because the token is single-use and consuming it on GET meant anything
     * that merely *fetched* the URL logged nobody in and destroyed the link: mobile mail
     * clients and in-app browsers prefetch links to build previews, and tokens were being
     * marked used 14 seconds after being issued. Desktop mail did not do it, which is why
     * this only ever failed on phones.
     *
     * Prefetchers issue GET and never POST, so this is immune rather than mitigated.
     */
    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        try {
            if (strtoupper($request->getMethod()) !== 'POST') {
                $token = $request->getQueryParams()['token'] ?? null;
                if (!$token) {
                    return $this->respond('donor/invalidVerifyEmailToken');
                }

                // Validates without consuming — verifyToken() only reads; it is
                // MagicLinkAuthenticator that invalidates afterwards. Checking here means an
                // expired link says so immediately instead of after a pointless click.
                $this->tokenRepository->verifyToken($token);

                return $this->respond('donor/confirmMagicLink', ['token' => $token]);
            }

            $token = $request->getParsedBody()['token'] ?? null;
            if (!$token) {
                return $this->respond('donor/invalidVerifyEmailToken');
            }
            $credentials = new MagicLinkCredentials($token, 'donor');
            $donor = $this->authenticatorRegistry->authenticate($credentials);   // validates + consumes the token
            $verifyingAfterRegister = $donor->status === \Solidarity\Donor\Entity\Donor::STATUS_NEW;

            // Who may log in is Donor::isActive()'s call, and the authenticator has already
            // made it — DELETED / PROBLEM throw before this line. The repeat here is belt and
            // braces against a valid token for a disabled account, nothing more. It used to
            // be a stricter test (status === VERIFIED) that bounced everyone else to the home
            // page with no session and a spent token; that contradicted isActive(), which
            // admits the two unpaid-instruction flags on purpose, so every donor the
            // flagDonors cron had flagged was silently locked out: the mail arrived, the
            // button did nothing, and the link was dead on the second try.
            if (!$donor->isActive()) {
                return $this->redirect($this->locale->localizeUrl('/'));
            }

            if ($donor->status === \Solidarity\Donor\Entity\Donor::STATUS_NEW) {        // first click = email verified
                $donor->status = \Solidarity\Donor\Entity\Donor::STATUS_VERIFIED;
                $this->entityRegistry->getRepository('donor')->updateLoginInfo($donor); // persist
            } elseif ($donor->status === \Solidarity\Donor\Entity\Donor::STATUS_TRY_TO_CONTACT) {
                // The flag means "never came back"; logging in is coming back, so it is lifted
                // here rather than waiting for the cron to notice a moved lastVisit.
                // statusChangedAt moves with it so ExpireInstructions restarts the miss streak
                // from now instead of re-flagging on the old history. IGNORING_PAYMENTS is left
                // alone: that one is cleared by paying (ConfirmPayment), not by showing up.
                $donor->status = \Solidarity\Donor\Entity\Donor::STATUS_VERIFIED;
                $donor->statusChangedAt = new \DateTime();
                $this->entityRegistry->getRepository('donor')->updateLoginInfo($donor);
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