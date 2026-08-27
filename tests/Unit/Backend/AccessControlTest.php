<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Skeletor\Core\Acl\Acl;
use Skeletor\Core\Security\Authentication\AuthenticatableInterface;
use Skeletor\Core\Security\Authorization\AuthorizationService;
use Skeletor\Core\Security\Authorization\PermissionRegistry;
use Solidarity\User\Entity\User;

/**
 * Who can reach what, asserted against the real config/backend/permissions.php.
 *
 * Access is decided by AuthMiddleware, which runs with useVoters = true and therefore
 * consults the permissions map — not config/backend/acl.php, which now only supplies the
 * guest list and the flash messages. The map's defining property is that **an unmapped path
 * is denied to everybody, including admins**: a new endpoint is invisible until someone
 * remembers to map it, and the symptom is a silent 302 with a flash, not an error.
 *
 * That has already switched features off by accident (the delegate payout upload, and the
 * since-removed /educator/* section),
 * so the matrix below is written from the outside in: it lists the paths the admin
 * navigation actually offers each role, and the boundaries that must hold whatever else
 * changes. It covers config, not code — hence CoversNothing.
 */
#[CoversNothing]
final class AccessControlTest extends TestCase
{
    private const DELEGATE = 10;

    // ---- the invariant --------------------------------------------------------

    #[DataProvider('everyRole')]
    public function testAPathNobodyMappedIsDeniedToEveryone(int $role): void
    {
        // Not a hypothetical: this is how /transaction/uploadTransactionList and the whole
        // /educator/* section came to be unreachable. Deny-by-default is the intended
        // behaviour — the cost is that adding an endpoint is two edits, not one.
        self::assertFalse($this->canReach('/transaction/somethingNobodyMapped/', $role));
    }

    #[DataProvider('everyRole')]
    public function testTheRetiredSectionsStayUnreachable(int $role): void
    {
        // /educator/* (replaced by /beneficiary/) and the two spreadsheet importers have all
        // been removed — controllers, routes, permissions and ACL entries. Kept as
        // assertions because the paths outlived their code once already: they sat as live
        // routes pointing at deleted classes, which for an admin is a 500 rather than a 404.
        self::assertFalse($this->canReach('/educator/view/', $role));
        self::assertFalse($this->canReach('/educator/form/5', $role));
        self::assertFalse($this->canReach('/educatorImport/import/', $role));
        self::assertFalse($this->canReach('/transactionImport/import/', $role));
    }

    #[DataProvider('everyRole')]
    public function testTheDelegatePayoutUploadStaysSwitchedOff(int $role): void
    {
        // Deliberate for the first production release: the generator has no caller and the
        // reader is not mapped. If this test starts failing, the feature was turned back on
        // — check that TransactionController::uploadTransactionList() is ready for it
        // (it bypasses LOCKED_STATUSES) rather than just deleting the assertion.
        self::assertFalse($this->canReach('/transaction/uploadTransactionList/', $role));
        self::assertFalse($this->canReach('/transaction/uploadTransactionListForm/', $role));
    }

    /** @return array<string, array{int}> */
    public static function everyRole(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'staff' => [User::ROLE_STUFF],
            'delegate' => [self::DELEGATE],
        ];
    }

    // ---- delegates ------------------------------------------------------------

    #[DataProvider('theDelegatesOwnSurface')]
    public function testADelegateCanReachEverythingTheirNavigationOffers(string $path): void
    {
        // navigation.php shows delegates four sections. A permission map that disagrees
        // gives them a menu of links that bounce them back to their own profile.
        self::assertTrue($this->canReach($path, self::DELEGATE), $path);
    }

    /** @return array<string, array{string}> */
    public static function theDelegatesOwnSurface(): array
    {
        return [
            'transaction list' => ['/transaction/view/'],
            'transaction table data' => ['/transaction/tableHandler/'],
            // Delegates mark payouts paid or cancelled from the list, and that is the whole of
            // what they may do to a transaction — the full edit form is staff and admin only.
            'set a transaction status' => ['/transaction/updateStatus/5'],
            'set several transaction statuses' => ['/transaction/updateStatusBulk'],
            'beneficiary list' => ['/beneficiary/view/'],
            'beneficiary table data' => ['/beneficiary/tableHandler/'],
            'register a beneficiary' => ['/beneficiary/form/'],
            'edit a beneficiary' => ['/beneficiary/form/5'],
            // Their own record only — DelegateController::fetchTableData() narrows the rows.
            'delegate list' => ['/delegate/view/'],
            'edit their own profile' => ['/delegate/form/5'],
            'school list' => ['/school/view/'],
            'school table data' => ['/school/tableHandler/'],
        ];
    }

    #[DataProvider('closedToDelegates')]
    public function testADelegateCannotReachTheRestOfTheDashboard(string $path): void
    {
        self::assertFalse($this->canReach($path, self::DELEGATE), $path);
    }

    /** @return array<string, array{string}> */
    public static function closedToDelegates(): array
    {
        return [
            // The one that matters most: delegates are volunteers, donors are personal data.
            'the donor list' => ['/donor/view/'],
            'donor table data' => ['/donor/tableHandler/'],
            'a single donor' => ['/donor/form/5'],
            'user management' => ['/user/view/'],
            'the statistics dashboard' => ['/statistics'],
            'running the allocation cron by hand' => ['/createTransactions'],
            'creating a transaction' => ['/transaction/form/'],
            // Status only. Re-authoring a transaction — amount, donor, beneficiary — is not a
            // delegate's job, and transaction.edit no longer carries role 10.
            'editing a whole transaction' => ['/transaction/form/5'],
            'saving a whole transaction' => ['/transaction/update/5'],
            'deleting a transaction' => ['/transaction/delete/5'],
            'deleting a beneficiary' => ['/beneficiary/delete/5'],
            'deleting a delegate' => ['/delegate/delete/5'],
            'creating a school' => ['/school/create/'],
            'periods' => ['/period/view/'],
            'cities' => ['/city/view/'],
            'school types' => ['/schoolType/view/'],
            'the newsletter list' => ['/emails/view/'],
            'pages' => ['/page/view/'],
            'images' => ['/image/view/'],
            'files' => ['/file/view/'],
            'theme settings' => ['/theme/view/'],
            'navigation settings' => ['/navigation/view/'],
            'the translator' => ['/translator/view/'],
            'the activity log' => ['/activity/view/'],
        ];
    }

    // ---- staff ----------------------------------------------------------------

    #[DataProvider('theStaffSurface')]
    public function testStaffCanRunTheDayToDay(string $path): void
    {
        self::assertTrue($this->canReach($path, User::ROLE_STUFF), $path);
    }

    /** @return array<string, array{string}> */
    public static function theStaffSurface(): array
    {
        return [
            'donors' => ['/donor/view/'],
            'create a donor' => ['/donor/form/'],
            'beneficiaries' => ['/beneficiary/view/'],
            'transactions' => ['/transaction/view/'],
            'create a transaction' => ['/transaction/form/'],
            'delegates' => ['/delegate/view/'],
            'create a delegate' => ['/delegate/create/'],
            'schools' => ['/school/view/'],
            'statistics' => ['/statistics'],
        ];
    }

    #[DataProvider('adminOnly')]
    public function testTheDestructiveAndStructuralPartsAreAdminOnly(string $path): void
    {
        self::assertTrue($this->canReach($path, User::ROLE_ADMIN), $path . ' should be open to admins');
        self::assertFalse($this->canReach($path, User::ROLE_STUFF), $path . ' should be closed to staff');
        self::assertFalse($this->canReach($path, self::DELEGATE), $path . ' should be closed to delegates');
    }

    /** @return array<string, array{string}> */
    public static function adminOnly(): array
    {
        return [
            // Deletion is admin-only across the board — staff can create and edit, not remove.
            'delete a donor' => ['/donor/delete/5'],
            'bulk-delete donors' => ['/donor/deleteBulk/'],
            'delete a beneficiary' => ['/beneficiary/delete/5'],
            'delete a transaction' => ['/transaction/delete/5'],
            'delete a delegate' => ['/delegate/delete/5'],
            // Reference data the rest of the model hangs off.
            'manage schools' => ['/school/create/'],
            'manage periods' => ['/period/view/'],
            'manage cities' => ['/city/view/'],
            'manage school types' => ['/schoolType/view/'],
            // Everything that can send mail, change the site, or move money.
            'user management' => ['/user/view/'],
            'the newsletter list' => ['/emails/view/'],
            'pages' => ['/page/view/'],
            'run the allocation cron' => ['/createTransactions'],
            'theme settings' => ['/theme/view/'],
        ];
    }

    public function testAdminsInheritEverythingDelegatesCanDo(): void
    {
        // Every shared permission currently lists all three roles explicitly, so the
        // ROLE_ADMIN => [10] hierarchy is belt and braces rather than load-bearing. This
        // asserts the outcome, not the mechanism: however the two are reconciled later, an
        // admin must never be locked out of a screen a delegate can open.
        foreach (self::theDelegatesOwnSurface() as $label => [$path]) {
            self::assertTrue($this->canReach($path, User::ROLE_ADMIN), $label);
        }
    }

    // ---- the guest surface ------------------------------------------------------

    #[DataProvider('guestPaths')]
    public function testTheWayInIsOpenWithoutASession(string $path): void
    {
        // Guest paths skip AuthMiddleware entirely, before any permission is consulted. If
        // the login form stopped being one, the middleware would redirect an anonymous
        // visitor to the login form — which is the page it just refused to serve.
        self::assertTrue($this->acl()->isGuestPath($path), $path);
    }

    /** @return array<string, array{string}> */
    public static function guestPaths(): array
    {
        return [
            'the dashboard root' => ['/'],
            'the user login form' => ['/login/user/loginForm/'],
            'the delegate magic-link form' => ['/login/delegate/magicLinkForm/'],
            'requesting a magic link' => ['/login/delegate/requestMagicLink/'],
            'following a magic link' => ['/login/delegate/verifyMagicLink/abc123'],
            // Not in permissions.php at all, so it is reachable *only* because it counts as
            // a guest path. Tighten the guest list and logged-in users cannot log out.
            'logging out' => ['/login/logout/'],
        ];
    }

    public function testTheGuestListDoesNotHandOutTheDashboard(): void
    {
        self::assertFalse($this->acl()->isGuestPath('/donor/view/'));
        self::assertFalse($this->acl()->isGuestPath('/transaction/view/'));
        self::assertFalse($this->acl()->isGuestPath('/statistics'));
    }

    public function testAGuestWildcardMatchesAnywhereInThePathNotJustAtTheStart(): void
    {
        // isGuestPath() takes the text before the '*' and runs strpos(), so '/login/*/...'
        // whitelists any path *containing* '/login/'. Harmless today — FastRoute's {id}
        // placeholder cannot contain a slash, so no real route can smuggle one in — but it
        // is why '/login/user/loginForm/' and '/login/logout/' are guest paths despite
        // matching no entry literally. Worth knowing before adding a free-text segment.
        self::assertTrue($this->acl()->isGuestPath('/anything/login/at-all'));
    }

    // ---- collaborators -----------------------------------------------------------

    private function canReach(string $path, int $role): bool
    {
        $service = new AuthorizationService(
            new PermissionRegistry(require APP_PATH . '/config/backend/permissions.php'),
            new NullLogger(),
        );

        $entity = $this->createStub(AuthenticatableInterface::class);
        $entity->method('getAuthRole')->willReturn($role);

        return $service->canAccessPath($path, $entity);
    }

    private function acl(): Acl
    {
        return new Acl(
            $this->createStub(SessionManager::class),
            // isGuestPath() substitutes adminPath for the literal 'admin' in each entry;
            // no entry contains it, but the property has to exist.
            new Config(['adminPath' => '']),
            require APP_PATH . '/config/backend/acl.php',
            [],
        );
    }
}
