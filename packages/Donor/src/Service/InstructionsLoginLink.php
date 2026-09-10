<?php
declare(strict_types=1);

namespace Solidarity\Donor\Service;

use Skeletor\Core\Config\Config;
use Skeletor\Core\Login\Exception\InvalidCredentials;
use Skeletor\Core\Login\Repository\MagicLinkTokenRepository;
use Skeletor\Core\Login\Service\MagicLinkService;
use Skeletor\Core\Login\Service\TokenGenerator;
use Solidarity\Donor\Entity\Donor;

/**
 * Login link for the payment-instructions email.
 *
 * Deliberately not MagicLinkService::requestMagicLink(). That method reads its lifetime from
 * magicLink.expiryMinutes, which is 15 — right for "I just asked to log in", wrong for a mail
 * announcing a 72-hour payment window, where the CTA would be dead before most donors opened
 * it. The service takes no per-call expiry, and raising the global one would lengthen the
 * window on admin and delegate links too, which are the only credential this app has.
 *
 * The two gates that matter are reproduced rather than skipped: the account must be allowed
 * in, and any link already in flight is invalidated so a forwarded older mail cannot be used
 * behind the donor. The cooldown is not — it exists to stop someone hammering the public
 * login form, and this is a cron mailing a donor who did not ask for anything.
 */
class InstructionsLoginLink
{
    /** 72 hours, matching Transaction::getExpiryDate() and what the email itself promises. */
    private const DEFAULT_EXPIRY_MINUTES = 4320;

    public function __construct(
        private TokenGenerator $tokenGenerator,
        private MagicLinkTokenRepository $tokenRepository,
        private MagicLinkService $magicLinkService,
        private Config $config
    ) {}

    /**
     * @return string the full URL for the email's CTA
     * @throws InvalidCredentials the donor may not log in
     */
    public function issue(Donor $donor): string
    {
        if (!$donor->isActive()) {
            throw new InvalidCredentials('Donor account is not active.');
        }

        $this->tokenRepository->invalidateAllForEntity('donor', (int) $donor->getId());

        $token = $this->tokenGenerator->generate(64);
        $this->tokenRepository->create($token, 'donor', (int) $donor->getId(), $this->expiryMinutes());

        // Built from magicLink.urls.donor, so the public destination stays in config with
        // every other entity type instead of being concatenated a second time here.
        return $this->magicLinkService->buildMagicLinkUrl($token, 'donor');
    }

    private function expiryMinutes(): int
    {
        $configured = (int) ($this->config->magicLink?->instructionsExpiryMinutes ?? 0);

        return $configured > 0 ? $configured : self::DEFAULT_EXPIRY_MINUTES;
    }
}
