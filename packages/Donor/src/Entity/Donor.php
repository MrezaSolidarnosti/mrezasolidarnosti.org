<?php

namespace Solidarity\Donor\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Timestampable;
use Skeletor\Core\Security\Authentication\AuthenticatableInterface;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

#[ORM\Entity]
#[ORM\Table(name: 'donor')]
class Donor implements AuthenticatableInterface
{
    use Timestampable;

    const STATUS_NEW = 1;
    const STATUS_VERIFIED = 2;
    const STATUS_PROBLEM = 3;
    const STATUS_DELETED = 4;

    // Values aligned with the legacy app's UserDonor::SCHOOL_TYPE_* so the data
    // migration is a direct copy (ALL=1, UNIVERSITY/UNI=2, EDUCATION/SCHOOL=3).
    const DONATE_TO_ALL = 1;
    const DONATE_TO_UNI = 2;
    const DONATE_TO_SCHOOL = 3;

    const ROLE_DONOR = 20;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $email;
    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $firstName;
    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $lastName;
    #[ORM\Column(type: Types::SMALLINT)]
    public int $status;
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $wantsToDonateTo;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $comment;
    #[ORM\Column(type: Types::INTEGER)]
    public string $isActive;
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    public ?string $ipv4;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTime $lastLogin;

    // Last time the donor loaded any page while logged in — distinct from lastLogin, which
    // only moves when a magic link is clicked. A session lives 30 days, so a donor can read
    // their instructions for weeks without ever logging in again; ExpireInstructions needs
    // "did they come back and see this", not "did they re-authenticate".
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTime $lastVisit = null;

    #[ORM\OneToMany(targetEntity: PaymentMethod::class, mappedBy: 'donor')]
    public Collection $paymentMethods;
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'donor')]
    public Collection $transactions;
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'donors')]
    #[ORM\JoinTable(name: 'donor_project')]
    public Collection $projects;

    public function __construct()
    {
        $this->paymentMethods = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }

    public function getId(): int|string { return $this->id; }
    public function getAuthIdentifier(): string { return $this->email; }
    public function getAuthPassword(): ?string { return null; }            // passwordless
    public function getAuthRole(): int { return self::ROLE_DONOR; }
    public function getRedirectPath(): string { return '/donor/profile/'; } // wherever donors land
    public function getEmail(): string { return $this->email; }
    public function getDisplayName(): ?string { return trim($this->firstName . ' ' . $this->lastName); }

    public function isActive(): bool
    {
        // NEW can authenticate (the click *is* the verification); DELETED/PROBLEM cannot.
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_VERIFIED], true);
    }

    public function supportsAuthenticator(string $type): bool { return $type === 'magic_link'; }

    public function updateLoginInfo(string $ip, \DateTime $time): void
    {
        $this->ipv4 = $ip;
        $this->lastLogin = $time;
    }

    public static function getHrStatuses(): array
    {
        return array(
            self::STATUS_NEW => 'New',
            self::STATUS_VERIFIED => 'Potvrdjen email',
            self::STATUS_PROBLEM => 'Problem',
            self::STATUS_DELETED => 'Obrisan',
        );
    }

    public static function getHrStatus($status): string
    {
        return static::getHrStatuses()[$status];
    }

    public static function getHrDonationOptions(): array
    {
        return array(
            self::DONATE_TO_ALL => 'Svima',
            self::DONATE_TO_SCHOOL => 'Prosveti',
            self::DONATE_TO_UNI => 'Univerzitetima',
        );
    }

    public static function getHrDonationOption($option): string
    {
        return static::getHrDonationOptions()[$option];
    }

    public function getPledgedAmountForProjectAndPaymentType($project, $filteredPm)
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if ($paymentMethod->project === $project && $paymentMethod->type === $filteredPm->type) {
                return $paymentMethod->amount;
            }
        }
        return 0;
    }

    public function getAmountForProject($project)
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if ($paymentMethod->project == $project) {
                return $paymentMethod->amount;
            }
        }
        return 0;
    }

    /**
     * Returns all payment methods for a given project.
     * @return PaymentMethod[]
     */
    public function getPaymentMethodsForProject(Project $project): array
    {
        $methods = [];
        foreach ($this->paymentMethods as $paymentMethod) {
            if ($paymentMethod->project->getId() === $project->getId()) {
                $methods[] = $paymentMethod;
            }
        }
        return $methods;
    }
}