<?php
namespace Solidarity\Frontend\Service;

use Laminas\Session\ManagerInterface as SessionManager;
use Skeletor\Core\Security\Authentication\AuthenticatableInterface;
use Skeletor\Core\Security\EntityRegistry;

/**
 * Frontend session reader — the public-site analog of Skeletor\User\Service\Session.
 *
 * The framework's LoginService::login($model, $entityType) writes the standard
 * keys (loggedIn, loggedInEntityType, loggedInRole, loggedInEmail, name...) for
 * every authenticatable, donors included. This wraps those keys with
 * donor/beneficiary-aware accessors so actions and templates don't poke at raw
 * session storage. The cheap getters never touch the DB; getUser() lazily
 * reloads the full entity through EntityRegistry only when a page needs it.
 *
 * Entity types live here so adding beneficiary later is a one-line change.
 */
class Session
{
    public const TYPE_DONOR = 'donor';
    public const TYPE_BENEFICIARY = 'beneficiary';

    private ?AuthenticatableInterface $user = null;
    private bool $userLoaded = false;

    public function __construct(
        private SessionManager $session,
        private EntityRegistry $entityRegistry,
    ) {}

    private function get(string $key)
    {
        return $this->session->getStorage()->offsetGet($key);
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->get('loggedIn');
    }

    public function getId(): ?int
    {
        $id = $this->get('loggedIn');
        return $id !== null ? (int) $id : null;
    }

    public function getEntityType(): ?string
    {
        return $this->get('loggedInEntityType');
    }

    public function getRole(): ?int
    {
        $role = $this->get('loggedInRole');
        return $role !== null ? (int) $role : null;
    }

    public function getEmail(): ?string
    {
        return $this->get('loggedInEmail');
    }

    public function getDisplayName(): ?string
    {
        $name = trim(sprintf('%s %s', (string) $this->get('loggedInFirstName'), (string) $this->get('loggedInLastName')));
        return $name !== '' ? $name : $this->getEmail();
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

    public function isDonor(): bool
    {
        return $this->isLoggedIn() && $this->getEntityType() === self::TYPE_DONOR;
    }

    public function isBeneficiary(): bool
    {
        return $this->isLoggedIn() && $this->getEntityType() === self::TYPE_BENEFICIARY;
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
            $repository->touchLastVisit($id);
        } catch (\Throwable $e) {
            // swallowed on purpose — see docblock
        }
    }

    /**
     * Lazily reload the logged-in entity (DB hit, cached per request).
     * Returns null when nobody is logged in or the record no longer exists.
     */
    public function getUser(): ?AuthenticatableInterface
    {
        if ($this->userLoaded) {
            return $this->user;
        }
        $this->userLoaded = true;

        $type = $this->getEntityType();
        $id = $this->getId();
        if (!$type || !$id || !$this->entityRegistry->has($type)) {
            return $this->user;
        }

        $repository = $this->entityRegistry->getRepository($type);
        if (method_exists($repository, 'getById')) {
            $entity = $repository->getById(['id' => $id]);
            if ($entity instanceof AuthenticatableInterface) {
                $this->user = $entity;
            }
        }

        return $this->user;
    }
}
