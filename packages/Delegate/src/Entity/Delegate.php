<?php

namespace Solidarity\Delegate\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Skeletor\Core\Entity\Loginable;
use Skeletor\Core\Entity\Timestampable;
use Skeletor\Core\Security\Authentication\AuthenticatableInterface;
use Solidarity\School\Entity\School;
use Solidarity\User\Entity\User;

#[ORM\Entity]
#[ORM\Table(name: 'delegate')]
class Delegate implements AuthenticatableInterface
{
    use Timestampable;

    const STATUS_NEW = 1;
    const STATUS_VERIFIED = 2;
    const STATUS_PROBLEM = 3;

//    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
//    public ?string $password;
    #[ORM\Column(type: Types::STRING, length: 128, unique: true, updatable: false)]
    public string $email;
    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $name;
    #[ORM\Column(type: Types::SMALLINT)]
    public int $status;
    #[ORM\Column(type: Types::STRING, length: 16)]
    public string $phone;
    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    public ?string $comment;
    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    public ?string $adminComment;
    #[ORM\Column(type: Types::STRING, length: 512)]
    public string $verifiedBy;
    #[ORM\Column(type: Types::INTEGER, nullable: true, options:["unsigned"=>true])]
    public ?string $ipv4;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTime $lastLogin;
    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'delegates')]
    #[ORM\JoinColumn(name: 'schoolId', referencedColumnName: 'id', unique: false, nullable: false)]
    public ?School $school;

    public static function getHrStatuses(): array
    {
        return array(
            self::STATUS_NEW => 'New',
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_PROBLEM => 'Problem',
        );
    }

    public static function getHrStatus($status): string
    {
        return static::getHrStatuses()[$status];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAuthIdentifier(): string
    {
        return $this->email;
    }

    public function getAuthPassword(): null
    {
        return null;
    }

    public function getAuthRole(): int
    {
        // Delegates have a fixed role @TODO
        return 10;
    }

    public function getRedirectPath(): string
    {
        return '/educator/view/';
    }

    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function supportsAuthenticator(string $authenticatorType): bool
    {
        // Delegates support password and magic link authentication
        return in_array($authenticatorType, ['password', 'magic_link']);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }

    public function updateLoginInfo($ipv4, $lastLogin): void
    {
        $this->ipv4 = $ipv4;
        $this->lastLogin = $lastLogin;
    }
}