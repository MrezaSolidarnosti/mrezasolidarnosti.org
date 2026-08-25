<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Solidarity\Backend\Controller\BeneficiaryController;
use Solidarity\Backend\Controller\DonorController;
use Solidarity\Backend\Service\Redaction;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Beneficiary\Service\Beneficiary as BeneficiaryService;
use Solidarity\Delegate\Service\Delegate as DelegateService;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Period\Service\Period as PeriodService;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\User\Entity\User;
use Tamtamchik\SimpleFlash\Flash;

/**
 * The delete buttons on the donor and beneficiary tables.
 *
 * Neither of these is a soft delete. Both hand off to Redaction, which erases the row for
 * good and keeps the transactions as anonymous accounting records — so the thing worth
 * asserting is not just "did it delete" but "did the money survive and the personal data
 * not". RedactionTest covers the service; nothing covered the two endpoints that reach it,
 * which is where the ids come from and where the answer is reported back to the admin.
 *
 * Both endpoints write JSON directly rather than rendering, which is why they can be driven
 * end to end here while the form() endpoints on the same controllers cannot.
 */
#[CoversClass(DonorController::class)]
#[CoversClass(BeneficiaryController::class)]
final class ErasureEndpointsTest extends IntegrationTestCase
{
    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        // SimpleFlash indexes $_SESSION without a guard when its process-wide engine is
        // first constructed, and BeneficiaryController::delete() flashes.
        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION = ['flash_messages' => []];
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }

        parent::tearDown();
    }

    // ---- erasing one donor ---------------------------------------------------------

    public function testErasingADonorRemovesThemAndLeavesTheirDonationsAsAnonymousRecords(): void
    {
        [$donor, $transaction] = $this->donorWithATransaction();
        $donorId = $donor->getId();
        $transactionId = $transaction->getId();

        $this->deleteDonor($donor);

        $this->em()->clear();
        self::assertNull($this->em()->find(Donor::class, $donorId), 'the donor row must be gone');

        // The money has to stay countable: period and project totals are built from these.
        $survivor = $this->em()->find(Transaction::class, $transactionId);
        self::assertNotNull($survivor);
        self::assertNull($survivor->donor);
        self::assertSame(5000, $survivor->amount);
    }

    public function testErasingADonorDropsThePledgesTheyLeftBehind(): void
    {
        // A pledge carries the amount and currency someone committed to; leaving it behind
        // would keep the cron allocating against a donor who asked to be forgotten.
        [$donor] = $this->donorWithATransaction();
        $pledge = $this->createDonorPaymentMethod($donor, $this->project(), 1, true, 15000);
        $pledgeId = $pledge->getId();

        $this->deleteDonor($donor);

        $this->em()->clear();
        self::assertNull($this->em()->find(DonorPaymentMethod::class, $pledgeId));
    }

    public function testAnIdMatchingNoDonorIsReportedAsAFailureRatherThanAnErasure(): void
    {
        // Double-clicking a stale row must not produce a second "permanently removed"
        // confirmation. It is the one action an admin may later be asked to account for, so
        // the endpoint has to distinguish "erased" from "there was nothing there".
        $decoded = $this->decode($this->deleteDonorById(999999));

        self::assertFalse($decoded['status']);
        self::assertSame('', $decoded['message']);
        self::assertSame([['message' => 'Donator nije pronađen.']], $decoded['generalErrors']);
    }

    // ---- erasing several donors at once -----------------------------------------------

    public function testBulkErasureRemovesEveryDonorInTheSelection(): void
    {
        [$first] = $this->donorWithATransaction();
        $second = $this->createDonor();
        $ids = [$first->getId(), $second->getId()];

        $response = $this->deleteDonorsBulk([$first, $second]);

        self::assertTrue($this->decode($response)['status']);
        $this->em()->clear();
        foreach ($ids as $id) {
            self::assertNull($this->em()->find(Donor::class, $id));
        }
    }

    public function testBulkErasureSkipsIdsThatMatchNothingAndCarriesOn(): void
    {
        // Two admins working the same list is the ordinary case; a already-deleted id in the
        // selection must not stop the rest of the batch.
        [$donor] = $this->donorWithATransaction();
        $donorId = $donor->getId();

        $response = $this->deleteDonorsBulkIds([999999, $donorId], [$donorId => $donor]);

        self::assertTrue($this->decode($response)['status']);
        $this->em()->clear();
        self::assertNull($this->em()->find(Donor::class, $donorId));
    }

    public function testABulkRequestWithNoIdsErasesNothingAndSaysSo(): void
    {
        [$donor] = $this->donorWithATransaction();
        $donorId = $donor->getId();

        $response = $this->deleteDonorsBulkIds([], [$donorId => $donor]);
        $decoded = $this->decode($response);

        self::assertFalse($decoded['status']);
        self::assertSame('Could not delete entity', $decoded['message']);
        self::assertNotSame([], $decoded['generalErrors']);
        $this->em()->clear();
        self::assertNotNull($this->em()->find(Donor::class, $donorId));
    }

    // ---- erasing a beneficiary ----------------------------------------------------------

    public function testErasingABeneficiaryStripsTheirBankDetailsOffPastTransactions(): void
    {
        // The sharp end of this: a transaction carries a *copy* of the account number it was
        // paid into. Deleting the beneficiary row alone would leave that copy sitting in the
        // transaction table, which is exactly the data the erasure is meant to remove.
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary('To be erased');
        $transaction = $this->createTransaction($donor, $beneficiary, $this->project(), $this->period(), 7000);
        $transaction->accountNumber = '000000000000000098';
        $transaction->instructions = 'IBAN RS35000000000000000098';
        $this->em()->flush();
        $transactionId = $transaction->getId();
        $beneficiaryId = $beneficiary->getId();

        $this->deleteBeneficiary($beneficiary);

        $this->em()->clear();
        self::assertNull($this->em()->find(Beneficiary::class, $beneficiaryId));

        $survivor = $this->em()->find(Transaction::class, $transactionId);
        self::assertNotNull($survivor);
        self::assertNull($survivor->accountNumber);
        self::assertNull($survivor->instructions);
        self::assertNull($survivor->beneficiary);
        self::assertSame(7000, $survivor->amount);
    }

    public function testErasingABeneficiaryRemovesTheirPaymentMethodsAndRegistrations(): void
    {
        $beneficiary = $this->createBeneficiary('To be erased');
        $paymentMethod = $this->createBeneficiaryPaymentMethod($beneficiary);
        $registration = $this->createRegisteredPeriod($beneficiary, $this->project(), $this->period(), 40000);
        $paymentMethodId = $paymentMethod->getId();
        $registrationId = $registration->getId();

        $this->deleteBeneficiary($beneficiary);

        $this->em()->clear();
        self::assertNull($this->em()->find(BeneficiaryPaymentMethod::class, $paymentMethodId));
        self::assertNull($this->em()->find(RegisteredPeriods::class, $registrationId));
    }

    public function testAnIdMatchingNoBeneficiaryIsReportedAsAFailureRatherThanAnErasure(): void
    {
        $decoded = $this->decode($this->deleteBeneficiaryById(999999));

        self::assertFalse($decoded['status']);
        self::assertSame([['message' => 'Oštećeni nije pronađen.']], $decoded['generalErrors']);
    }

    public function testNothingFoundMeansNoErasureConfirmationIsFlashedEither(): void
    {
        // The flash outlives the response and is what the admin actually reads on the next
        // page. A success flash on a stale row is a written record of an erasure that never
        // happened, which is worse than the misleading payload it accompanied.
        $this->deleteBeneficiaryById(999999);

        self::assertSame([], $_SESSION['flash_messages']['success'] ?? []);
    }

    public function testARealErasureStillConfirmsItself(): void
    {
        $beneficiary = $this->createBeneficiary('To be erased');

        $decoded = $this->decode($this->deleteBeneficiary($beneficiary));

        self::assertSame(1, $decoded['status']);
        self::assertSame('Podaci oštećenog su trajno uklonjeni.', $decoded['message']);
        self::assertContains('Podaci oštećenog su trajno uklonjeni.', $_SESSION['flash_messages']['success']);
    }

    // ---- fixtures ---------------------------------------------------------------------------

    private ?\Solidarity\Transaction\Entity\Project $projectFixture = null;
    private ?\Solidarity\Period\Entity\Period $periodFixture = null;

    private function project(): \Solidarity\Transaction\Entity\Project
    {
        return $this->projectFixture ??= $this->createProject();
    }

    private function period(): \Solidarity\Period\Entity\Period
    {
        return $this->periodFixture ??= $this->createPeriod($this->project());
    }

    /** @return array{Donor, Transaction} */
    private function donorWithATransaction(): array
    {
        $donor = $this->createDonor();
        $transaction = $this->createTransaction(
            $donor, $this->createBeneficiary('Recipient'), $this->project(), $this->period(), 5000,
        );

        return [$donor, $transaction];
    }

    // ---- driving the endpoints ------------------------------------------------------------------

    private function deleteDonor(Donor $donor): ResponseInterface
    {
        return $this->deleteDonorById((int) $donor->getId(), [(int) $donor->getId() => $donor]);
    }

    /** @param array<int, Donor> $known id => entity, as the service would resolve them */
    private function deleteDonorById(int $id, array $known = []): ResponseInterface
    {
        $controller = $this->donorController($known);
        $controller->setRequest(
            (new ServerRequest('GET', '/donor/delete/'))->withAttribute('id', (string) $id),
        );

        return $controller->delete();
    }

    /** @param Donor[] $donors */
    private function deleteDonorsBulk(array $donors): ResponseInterface
    {
        $known = [];
        foreach ($donors as $donor) {
            $known[(int) $donor->getId()] = $donor;
        }

        return $this->deleteDonorsBulkIds(array_keys($known), $known);
    }

    /**
     * @param int[]             $ids   an empty list omits the key entirely, as the front end does
     * @param array<int, Donor> $known id => entity
     */
    private function deleteDonorsBulkIds(array $ids, array $known): ResponseInterface
    {
        $controller = $this->donorController($known);
        $controller->setRequest(new ServerRequest(
            'POST', '/donor/deleteBulk/', [], json_encode($ids === [] ? [] : ['ids' => $ids]),
        ));

        return $controller->deleteBulk();
    }

    private function deleteBeneficiary(Beneficiary $beneficiary): ResponseInterface
    {
        return $this->deleteBeneficiaryById(
            (int) $beneficiary->getId(),
            [(int) $beneficiary->getId() => $beneficiary],
        );
    }

    /** @param array<int, Beneficiary> $known */
    private function deleteBeneficiaryById(int $id, array $known = []): ResponseInterface
    {
        $controller = $this->beneficiaryController($known);
        $controller->setRequest(
            (new ServerRequest('GET', '/beneficiary/delete/'))->withAttribute('id', (string) $id),
        );

        return $controller->delete();
    }

    // ---- collaborators -------------------------------------------------------------------------------

    /** @param array<int, Donor> $known */
    private function donorController(array $known): DonorController
    {
        return new DonorController(
            $this->lookupService(DonorService::class, $known),
            $this->session(),
            new Config(['adminPath' => '']),
            new Flash(),
            $this->engine(),
            $this->createStub(ProjectService::class),
            new Redaction($this->em()),
        );
    }

    /** @param array<int, Beneficiary> $known */
    private function beneficiaryController(array $known): BeneficiaryController
    {
        return new BeneficiaryController(
            $this->lookupService(BeneficiaryService::class, $known),
            $this->session(),
            new Config(['adminPath' => '']),
            new Flash(),
            $this->engine(),
            $this->createStub(SchoolService::class),
            $this->createStub(PeriodService::class),
            $this->createStub(ProjectService::class),
            $this->createStub(DelegateService::class),
            $this->createStub(TransactionService::class),
            new Redaction($this->em()),
        );
    }

    /**
     * Unlike a plain stub, this one honours the id it is given — which matters here, because
     * "the id matched nothing" is one of the behaviours under test.
     *
     * @template T of object
     * @param class-string<T>     $class
     * @param array<int, object>  $known
     * @return T
     */
    private function lookupService(string $class, array $known): object
    {
        $service = $this->createStub($class);
        $service->method('getById')->willReturnCallback(
            static fn ($id) => $known[(int) $id] ?? null,
        );

        return $service;
    }

    private function session(): SessionManager
    {
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn(new ArrayStorage([
            'loggedIn' => 1,
            'loggedInRole' => User::ROLE_ADMIN,
            'loggedInEntityType' => 'user',
        ]));

        return $session;
    }

    /** deleteBulk() translates its messages, and Controller::translate() goes through Plates. */
    private function engine(): Engine
    {
        $engine = new Engine();
        $engine->registerFunction('t', static fn (string $string): string => $string);

        return $engine;
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
