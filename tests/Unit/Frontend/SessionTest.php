<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Frontend;

use Laminas\Session\ManagerInterface as SessionManager;
use Laminas\Session\Storage\SessionArrayStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Security\EntityRegistry;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Frontend\Service\Session;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $session = null;

    protected function setUp(): void
    {
        // SessionArrayStorage is backed by $_SESSION, so snapshot it.
        $this->session = $_SESSION ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->session === null) {
            unset($_SESSION);

            return;
        }
        $_SESSION = $this->session;
    }

    // ---- the cheap accessors ----------------------------------------------

    public function testIsLoggedInReflectsThePresenceOfAnId(): void
    {
        self::assertTrue($this->session(['loggedIn' => 7])->isLoggedIn());
        self::assertFalse($this->session([])->isLoggedIn());
    }

    public function testGetIdCastsToIntAndIsNullWhenAbsent(): void
    {
        // The framework writes the id as a string; every caller treats it as an int.
        self::assertSame(7, $this->session(['loggedIn' => '7'])->getId());
        self::assertNull($this->session([])->getId());
    }

    public function testGetRoleCastsToInt(): void
    {
        self::assertSame(20, $this->session(['loggedInRole' => '20'])->getRole());
    }

    public function testGetEmailAndEntityTypeArePassedThrough(): void
    {
        $session = $this->session([
            'loggedInEmail' => 'donor@example.com',
            'loggedInEntityType' => Session::TYPE_DONOR,
        ]);

        self::assertSame('donor@example.com', $session->getEmail());
        self::assertSame('donor', $session->getEntityType());
    }

    public function testDisplayNameJoinsFirstAndLastName(): void
    {
        $session = $this->session([
            'loggedInFirstName' => 'Petar',
            'loggedInLastName' => 'Petrović',
            'loggedInEmail' => 'petar@example.com',
        ]);

        self::assertSame('Petar Petrović', $session->getDisplayName());
    }

    public function testDisplayNameFallsBackToTheEmailWhenNoNameIsStored(): void
    {
        // Donors register with an email only, so this is the common case, not an edge one.
        self::assertSame(
            'petar@example.com',
            $this->session(['loggedInEmail' => 'petar@example.com'])->getDisplayName(),
        );
    }

    // ---- entity-type predicates -------------------------------------------

    public function testIsDonorRequiresBothALoginAndTheDonorType(): void
    {
        self::assertTrue($this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ])->isDonor());

        // Right type, nobody logged in.
        self::assertFalse($this->session(['loggedInEntityType' => Session::TYPE_DONOR])->isDonor());

        // Logged in, but as something else — a delegate must never pass a donor guard.
        self::assertFalse($this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => 'delegate',
        ])->isDonor());
    }

    public function testIsBeneficiaryRequiresBothALoginAndTheBeneficiaryType(): void
    {
        self::assertTrue($this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_BENEFICIARY,
        ])->isBeneficiary());

        self::assertFalse($this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ])->isBeneficiary());
    }

    // ---- preferred locale --------------------------------------------------

    public function testPreferredLocaleIsNullUnlessAMeaningfulStringIsStored(): void
    {
        self::assertSame('en', $this->session(['preferredLocale' => 'en'])->getPreferredLocale());
        self::assertNull($this->session(['preferredLocale' => ''])->getPreferredLocale());
        self::assertNull($this->session([])->getPreferredLocale());
    }

    // ---- getUser ------------------------------------------------------------

    public function testGetUserIsNullWhenNobodyIsLoggedIn(): void
    {
        $registry = $this->createMock(EntityRegistry::class);
        $registry->expects(self::never())->method('getRepository');

        self::assertNull($this->session([], $registry)->getUser());
    }

    public function testGetUserLoadsTheEntityThroughTheRegistry(): void
    {
        $donor = new Donor();

        $repository = $this->createMock(DonorRepository::class);
        // expects() is required alongside with(): from PHPUnit 14 the argument constraint
        // is silently ignored without it, so the id would never actually be asserted.
        $repository->expects(self::once())->method('getById')->with(['id' => 7])->willReturn($donor);

        self::assertSame($donor, $this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ], $this->registryFor($repository))->getUser());
    }

    public function testGetUserIsLoadedAtMostOncePerRequest(): void
    {
        $repository = $this->createMock(DonorRepository::class);
        $repository->expects(self::once())->method('getById')->willReturn(new Donor());

        $session = $this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ], $this->registryFor($repository));

        $session->getUser();
        $session->getUser();
    }

    public function testGetUserIsNullWhenTheEntityTypeIsNotRegistered(): void
    {
        $registry = $this->createMock(EntityRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects(self::never())->method('getRepository');

        self::assertNull($this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => 'martian',
        ], $registry)->getUser());
    }

    // ---- touchVisit ---------------------------------------------------------

    public function testTouchVisitStampsTheLoggedInDonor(): void
    {
        $repository = $this->createMock(DonorRepository::class);
        $repository->expects(self::once())->method('touchLastVisit')->with(7);

        $this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ], $this->registryFor($repository))->touchVisit();
    }

    public function testTouchVisitDoesNothingForAGuest(): void
    {
        $repository = $this->createMock(DonorRepository::class);
        $repository->expects(self::never())->method('touchLastVisit');

        $this->session([], $this->registryFor($repository))->touchVisit();
    }

    public function testTouchVisitDoesNothingForANonDonor(): void
    {
        // It runs on every frontend request; a delegate session must not be stamped.
        $repository = $this->createMock(DonorRepository::class);
        $repository->expects(self::never())->method('touchLastVisit');

        $this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => 'delegate',
        ], $this->registryFor($repository))->touchVisit();
    }

    public function testTouchVisitSwallowsRepositoryFailures(): void
    {
        // A visit stamp is never worth 500-ing a page over. A stub, not a mock: the
        // assertion is that nothing escapes, not that the repository was called.
        $repository = $this->createStub(DonorRepository::class);
        $repository->method('touchLastVisit')->willThrowException(new \RuntimeException('db is down'));

        $this->session([
            'loggedIn' => 7,
            'loggedInEntityType' => Session::TYPE_DONOR,
        ], $this->registryFor($repository))->touchVisit();

        $this->expectNotToPerformAssertions();
    }

    // ---- helpers ------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function session(array $data, ?EntityRegistry $registry = null): Session
    {
        // The real storage rather than a double, for two reasons: it is what the manager
        // defaults to in production, and its offsetGet resolves a missing key to null
        // instead of warning — which Session::get() relies on for every guest request.
        // Stubbing StorageInterface would also generate a class implementing the
        // deprecated Serializable, which SessionArrayStorage itself avoids.
        $storage = new SessionArrayStorage($data);

        $manager = $this->createStub(SessionManager::class);
        $manager->method('getStorage')->willReturn($storage);

        return new Session($manager, $registry ?? $this->createStub(EntityRegistry::class));
    }

    private function registryFor(DonorRepository $repository): EntityRegistry
    {
        $registry = $this->createStub(EntityRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        return $registry;
    }
}
