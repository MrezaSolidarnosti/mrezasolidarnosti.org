<?php
namespace Solidarity\Frontend\Service;

use Laminas\Session\ManagerInterface as SessionManager;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Core\Security\SessionContext;

/**
 * Frontend session reader.
 *
 * Everything generic — is anyone logged in, which entity type, the cheap name/email getters,
 * the lazy getUser() through the entity registry — now lives in Skeletor\Core\Security\
 * SessionContext, where the backend and any other app can use it too. What is left here is
 * the part that is genuinely about this site: which entity types the public pages care
 * about, the visitor's chosen locale, and the donor visit stamp.
 */
class Session extends SessionContext
{
    public const TYPE_DONOR = 'donor';
    public const TYPE_BENEFICIARY = 'beneficiary';

    public function __construct(SessionManager $session, EntityRegistry $entityRegistry)
    {
        parent::__construct($session, $entityRegistry);
    }

    /**
     * Narrowed to int, which is what every caller here passes straight into a query or an id
     * column. The base class returns int|string because an entity id need not be numeric.
     */
    public function getId(): ?int
    {
        $id = parent::getId();

        return $id !== null ? (int) $id : null;
    }

    public function isDonor(): bool
    {
        return $this->is(self::TYPE_DONOR);
    }

    public function isBeneficiary(): bool
    {
        return $this->is(self::TYPE_BENEFICIARY);
    }

    /** The locale the visitor explicitly chose via the language switcher, if any. */
    public function getPreferredLocale(): ?string
    {
        $locale = $this->get('preferredLocale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function setPreferredLocale(string $locale): void
    {
        $this->session->getStorage()->offsetSet('preferredLocale', $locale);
    }

    /**
     * Record that the logged-in donor is here, for ExpireInstructions to tell "never came
     * back" from "came back and did not pay". Deliberately does not hydrate the entity —
     * this runs on every request, so it delegates to a throttled UPDATE. Silent on failure:
     * a visit stamp is never worth breaking a page render over.
     */
    public function touchVisit(): void
    {
        if (!$this->isDonor()) {
            return;
        }

        $id = $this->getId();
        $type = $this->getEntityType();
        if (!$id || !$type || !$this->entityRegistry->has($type)) {
            return;
        }

        $repository = $this->entityRegistry->getRepository($type);
        if (!method_exists($repository, 'touchLastVisit')) {
            return;
        }

        try {
            $repository->touchLastVisit((int) $id);
        } catch (\Throwable $e) {
            // swallowed on purpose — see docblock
        }
    }
}
