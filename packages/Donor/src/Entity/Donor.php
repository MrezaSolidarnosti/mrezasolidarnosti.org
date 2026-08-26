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

    /**
     * Set by ExpireInstructions when a donor's instructions keep going unpaid, cleared by a
     * human (or by the donor paying — see Frontend\Action\Donor\ConfirmPayment).
     *
     * Both are excluded from allocation, because DonorRepository::getDonorsByProject() admits
     * only NEW and VERIFIED. Neither blocks login: an unpaid instruction reserves a
     * beneficiary's need, so the point is to stop *generating* new ones, not to lock the
     * person out. TRY_TO_CONTACT in particular is set precisely because the donor never came
     * back — barring them from returning would be self-defeating.
     */
    const STATUS_TRY_TO_CONTACT = 5;
    const STATUS_IGNORING_PAYMENTS = 6;

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

    /**
     * When this donor's status last changed, and therefore where the unpaid-instruction
     * count starts over.
     *
     * Without it the auto-flagging is a loop: an admin clears a flagged donor, the same
     * historical misses are still in the table, and the next expire run re-flags them within
     * the round. "Misses since their last honoured payment" cannot serve instead — a flagged
     * donor is allocated nothing, so they can never honour anything, and the streak could
     * never reset. NULL means "never changed", i.e. count their whole history.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTime $statusChangedAt = null;

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
        // The two unpaid-instruction flags deliberately CAN: they stop allocation, not access.
        // TRY_TO_CONTACT exists because the donor never came back, so locking them out would
        // defeat the only remedy it has; IGNORING_PAYMENTS is a shadow ban, and paying an
        // outstanding instruction is how a donor clears it themselves.
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_VERIFIED,
            self::STATUS_TRY_TO_CONTACT,
            self::STATUS_IGNORING_PAYMENTS,
        ], true);
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
            // Mandatory, not cosmetic: getHrStatus() returns getHrStatuses()[$status] with a
            // string return type, so an unlisted status is a TypeError and the donor table
            // fatals on the first flagged row.
            self::STATUS_TRY_TO_CONTACT => 'Za kontakt',
            self::STATUS_IGNORING_PAYMENTS => 'Ignoriše uplate',
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