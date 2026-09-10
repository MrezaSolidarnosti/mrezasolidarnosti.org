<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\Core\Config\Config;
use Solidarity\Backend\Action\CreateTransaction;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Service\InstructionsLoginLink;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Project as ProjectEntity;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * Who gets told about a round, and who does not.
 *
 * The instructions mail says "Stigle su ti nove instrukcije za uplatu" and names no
 * transaction — it just points at the instructions page. So a donor mailed after a round that
 * allocated them nothing arrives to find whatever was already outstanding, and reads it as a
 * re-send of an old instruction. That is a donor-facing correctness problem, not a cosmetic
 * one, which is why the decision is pinned here.
 */
#[CoversClass(CreateTransaction::class)]
final class CreateTransactionMailTest extends IntegrationTestCase
{
    public function testADonorWhoWasAllocatedSomethingIsMailed(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())->method('sendDonorInstructionsMail');

        $this->runRound(allocated: 5000, mailer: $mailer);
    }

    public function testADonorWhoWasAllocatedNothingIsNotMailed(): void
    {
        // The reported symptom: no instruction created for them in this round, yet an email
        // arrived pointing at an old one.
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::never())->method('sendDonorInstructionsMail');

        $this->runRound(allocated: 0, mailer: $mailer);
    }

    public function testADryRunMailsNobodyEvenWhenItWouldAllocate(): void
    {
        // A rollback cannot recall an email, so the preview must stay silent.
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::never())->method('sendDonorInstructionsMail');

        $this->runRound(allocated: 5000, mailer: $mailer, dry: true);
    }

    public function testAFailingMailDoesNotStopTheRestOfTheRound(): void
    {
        // How a dry run can list a full round while the real one commits a single transaction:
        // the mail is the only thing a real run does that a preview does not, and it used to
        // run unguarded inside the loop. One throw propagated out, the outer catch rethrew it,
        // and because a real round is on autocommit the first donor's allocation stayed while
        // every later donor was skipped in silence.
        //
        // Both donors must still be attempted. Whether the notification arrives is a separate
        // problem from whether the money moves.
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::exactly(2))
            ->method('sendDonorInstructionsMail')
            ->willThrowException(new \RuntimeException('mailer down'));

        $output = $this->runRound(allocated: 5000, mailer: $mailer, donorCount: 2);

        // And it says so rather than reporting a clean round.
        self::assertStringContainsString('2 instruction mail(s) failed', $output);
    }

    public function testAFailingLoginLinkStillMailsTheInstructions(): void
    {
        // The CTA is a convenience; being told there is something to pay is the point. A donor
        // no token could be minted for — barred account, or the write refused — must still get
        // the mail, with the button falling back to the site, rather than the notification
        // being dropped over a broken button.
        $loginLinks = $this->createStub(InstructionsLoginLink::class);
        $loginLinks->method('issue')->willThrowException(new \RuntimeException('no link'));

        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())
            ->method('sendDonorInstructionsMail')
            ->with(self::anything(), self::anything(), null);

        $this->runRound(allocated: 5000, mailer: $mailer, loginLinks: $loginLinks);
    }

    /**
     * Runs the action against $donorCount donors and one project, with the allocator stubbed
     * to report $allocated. What the allocator does is covered by CreateBalancedForDonorTest;
     * the only thing under test here is what the action does around it.
     */
    private function runRound(
        int $allocated,
        Mailer $mailer,
        bool $dry = false,
        int $donorCount = 1,
        ?InstructionsLoginLink $loginLinks = null
    ): string {
        $project = $this->createProject('MSPR');
        $donorList = [];
        for ($i = 0; $i < $donorCount; $i++) {
            $donorList[] = $this->createDonor();
        }

        // createStub, not createMock: nothing here asserts on the allocator, it only needs to
        // report a number. A mock with no expectations is what PHPUnit warns about.
        $transactions = $this->createStub(TransactionService::class);
        $transactions->method('createBalancedForDonor')->willReturn($allocated);

        $projects = $this->createStub(ProjectService::class);
        $projects->method('getEntities')->willReturn([$project]);

        $donors = $this->createStub(DonorService::class);
        $donors->method('getDonorsByProject')->willReturn($donorList);

        if ($loginLinks === null) {
            $loginLinks = $this->createStub(InstructionsLoginLink::class);
            $loginLinks->method('issue')->willReturn('https://example.test/donor/verifyEmail?token=t');
        }

        // isHoliday() is overridden rather than left to the calendar: the real one reads
        // date('d.m') against a fixed list, so on 1 May the whole class would return early
        // and pass while asserting nothing.
        $action = new class (
            new NullLogger(), new Config([]), new Engine(),
            $transactions, $projects, $donors, $mailer, $loginLinks, $this->em(),
        ) extends CreateTransaction {
            public function isHoliday(): bool
            {
                return false;
            }
        };

        $request = (new ServerRequest('GET', '/'))->withAttribute('params', $dry ? ['dry'] : ['run']);

        ob_start();
        try {
            $action($request, new Response());
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
