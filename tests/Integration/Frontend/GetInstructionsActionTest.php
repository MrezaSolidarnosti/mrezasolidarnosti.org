<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Frontend\Action\Donor\GetInstructions;

/**
 * The endpoint the donor dashboard pages through their payment instructions.
 *
 * Everything it returns is personal: who the money goes to, how much, the reference code
 * and the account number to pay into. The service beneath it has its own test
 * (tests/Integration/Donor/GetInstructionsTest); what is asserted here is the layer a
 * browser actually reaches — the login gate, the CSRF check, and paging.
 */
#[CoversClass(GetInstructions::class)]
final class GetInstructionsActionTest extends FrontendActionTestCase
{
    public function testADonorGetsTheirOwnInstructions(): void
    {
        $donor = $this->donorWithInstructions(3);

        $response = $this->action($donor)($this->post(['page' => 1, 'perPage' => 10]), $this->emptyResponse());

        $data = $this->decode($response)['data'];

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(3, $data['instructions']['items']);
    }

    public function testAnAnonymousVisitorIsTurnedAwayWithNothing(): void
    {
        // 401 and, more to the point, no 'instructions' key at all — the body must not
        // carry someone's beneficiary names and account numbers to a visitor with no session.
        $response = $this->action(null)($this->post(['page' => 1, 'perPage' => 10]), $this->emptyResponse());

        self::assertSame(401, $response->getStatusCode());
        self::assertArrayNotHasKey('instructions', $this->decode($response)['data']);
    }

    public function testAForgedRequestIsRefusedBeforeAnythingIsRead(): void
    {
        // Regression guard, and the same mistake ConfirmPayment had: the check set a 401 and
        // then carried on, so a request that failed it still got a full body of the donor's
        // instructions. The status said no while the payload said yes.
        $donor = $this->donorWithInstructions(2);

        $response = $this->action($donor)(
            $this->forgedPost(['page' => 1, 'perPage' => 10]),
            $this->emptyResponse(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertArrayNotHasKey('instructions', $this->decode($response)['data']);
    }

    public function testAForgedRequestStillHandsBackAFreshToken(): void
    {
        // Otherwise a donor whose token merely expired is stuck on a page that can never
        // submit again.
        $donor = $this->donorWithInstructions(1);

        $response = $this->action($donor)($this->forgedPost(), $this->emptyResponse());

        self::assertNotEmpty($this->decode($response)['data']['token']);
    }

    // ---- paging -------------------------------------------------------------------

    public function testThePageSizeIsHonoured(): void
    {
        $donor = $this->donorWithInstructions(5);

        $response = $this->action($donor)($this->post(['page' => 1, 'perPage' => 2]), $this->emptyResponse());

        self::assertCount(2, $this->decode($response)['data']['instructions']['items']);
    }

    public function testTheSecondPageContinuesWhereTheFirstStopped(): void
    {
        // The second request carries the token the first response returned, because
        // Csrf::validate() rotates it on every success — see testATokenIsGoodForOneRequest.
        $donor = $this->donorWithInstructions(5);

        $first = $this->decode($this->action($donor)($this->post(['page' => 1, 'perPage' => 2]), $this->emptyResponse()));
        $second = $this->decode($this->action($donor)(
            $this->postWithToken($first['data']['token'], ['page' => 2, 'perPage' => 2]),
            $this->emptyResponse(),
        ));

        $firstIds = array_column($first['data']['instructions']['items'], 'id');
        $secondIds = array_column($second['data']['instructions']['items'], 'id');

        self::assertCount(2, $secondIds);
        self::assertSame([], array_intersect($firstIds, $secondIds), 'a page must not repeat the one before it');
    }

    public function testATokenIsGoodForOneRequestAndTheReplacementComesBackWithIt(): void
    {
        // Not a quirk of the test harness — Csrf::validate() regenerates the token whenever
        // it succeeds, so the dashboard has to read the new one out of each response. A
        // client that keeps posting the token from the rendered page works exactly once and
        // is then locked out, which looks like a session timeout and is not one.
        $donor = $this->donorWithInstructions(1);

        $first = $this->decode($this->action($donor)($this->post(['page' => 1, 'perPage' => 10]), $this->emptyResponse()));

        $replayed = $this->action($donor)($this->post(['page' => 1, 'perPage' => 10]), $this->emptyResponse());
        $withNewToken = $this->action($donor)(
            $this->postWithToken($first['data']['token'], ['page' => 1, 'perPage' => 10]),
            $this->emptyResponse(),
        );

        self::assertSame(401, $replayed->getStatusCode(), 'the same token must not work twice');
        self::assertSame(200, $withNewToken->getStatusCode());
    }

    public function testAMissingPageSizeFallsBackToADefaultRatherThanReturningNothing(): void
    {
        // The old `(int) $data['perPage'] ?? 10` could never reach its default — (int) of a
        // missing key is 0, not null — so omitting it warned about the undefined index and
        // then paged by zero, which setMaxResults(0) turns into an empty list.
        $donor = $this->donorWithInstructions(3);

        $response = $this->action($donor)($this->post(['page' => 1]), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(3, $this->decode($response)['data']['instructions']['items']);
    }

    public function testAPageNumberBelowOneIsTreatedAsTheFirstPage(): void
    {
        // page=0 would otherwise compute a negative offset, which the query layer rejects.
        $donor = $this->donorWithInstructions(2);

        $response = $this->action($donor)($this->post(['page' => 0, 'perPage' => 10]), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(2, $this->decode($response)['data']['instructions']['items']);
    }

    // ---- fixtures ------------------------------------------------------------------

    /**
     * The clear at the end is load-bearing. getInstructions() formats `createdAt`, which is
     * insertable:false — set by the database, absent on the objects this method just
     * persisted. Doctrine hands those same instances back from the identity map rather than
     * re-reading them, so without the clear the action dies on "must not be accessed before
     * initialization" instead of returning a row.
     */
    private function donorWithInstructions(int $count): Donor
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();

        for ($i = 0; $i < $count; $i++) {
            $this->createTransaction(
                $donor,
                $this->createBeneficiary('Beneficiary ' . $i),
                $project,
                $period,
                1000 + $i,
            );
        }

        $donorId = $donor->getId();
        $this->em()->clear();

        return $this->em()->find(Donor::class, $donorId);
    }

    private function action(?Donor $donor): GetInstructions
    {
        return new GetInstructions(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $this->realDonorService(),
            $this->navigation(),
            $this->socialLinks(),
            $this->session($donor),
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
