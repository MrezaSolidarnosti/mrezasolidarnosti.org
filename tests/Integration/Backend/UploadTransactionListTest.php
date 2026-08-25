<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Solidarity\Backend\Controller\TransactionController;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

/**
 * The other half of the delegate payout round trip.
 *
 * compileXlsxTransactionList() writes the file a delegate pays from (covered by
 * tests/Unit/Transaction/XlsxTransactionListTest.php); this is the reader that takes the
 * marked-up file back and writes transaction statuses straight from its cells. The two
 * never reference each other, so every test here builds its file with the *real generator*
 * and then edits it the way a delegate would — if either side's layout moves, this breaks.
 *
 * What makes it worth testing is the blast radius: a cell in column E decides whether a
 * payout is confirmed or cancelled, and the switch's default arm is STATUS_CANCELLED. There
 * is no confirmation step between the upload and the write.
 */
#[CoversClass(TransactionController::class)]
final class UploadTransactionListTest extends TransactionControllerTestCase
{
    private const PAID = 'Plaćeno';
    private const UNPAID = 'Neplaćeno';

    /** @var string[] files created by a test, removed in tearDown */
    private array $written = [];

    private ?Project $project = null;
    private ?Period $period = null;
    private ?Donor $donor = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The generator writes here for real; it is an application directory, not a temp one.
        if (!is_dir(DATA_PATH . '/lists')) {
            mkdir(DATA_PATH . '/lists', 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->written = [];
        $this->project = $this->period = $this->donor = null;

        parent::tearDown();
    }

    // ---- the two statuses the dropdown offers --------------------------------

    public function testAPaidRowConfirmsTheTransaction(): void
    {
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['E3' => self::PAID]));

        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
    }

    public function testAnUnpaidRowCancelsTheTransaction(): void
    {
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['E3' => self::UNPAID]));

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    public function testEveryRowIsAppliedIndependently(): void
    {
        $paid = $this->newTransaction(5000);
        $unpaid = $this->newTransaction(7000);
        $alsoPaid = $this->newTransaction(1200);

        $this->upload($this->delegateFile([$paid, $unpaid, $alsoPaid], [
            'E3' => self::PAID,
            'E4' => self::UNPAID,
            'E5' => self::PAID,
        ]));

        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($paid));
        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($unpaid));
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($alsoPaid));
    }

    // ---- the default arm ------------------------------------------------------

    public function testABlankStatusCellCancelsThePayout(): void
    {
        // The switch has no arm for an empty cell and falls through to
        // default: STATUS_CANCELLED. Nothing is flagged and no error is raised — the
        // delegate simply never gets paid for the row they skipped. The only guard is
        // allowBlank(false) on the dropdown, which Excel can be talked out of.
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], []));

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
        self::assertSame([], $this->flashErrors());
    }

    public function testAStatusTypedWithoutDiacriticsCancelsThePayout(): void
    {
        // The likeliest real-world version of the above: a delegate on a keyboard without
        // Serbian layout types "Placeno" over the dropdown. It is not "Plaćeno", so it
        // takes the default arm and cancels the very payout they just made.
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['E3' => 'Placeno']));

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    // ---- rows the reader refuses ---------------------------------------------

    public function testAnAmountThatNoLongerMatchesLeavesTheTransactionAlone(): void
    {
        // The amount check is what stops a stale list — one generated before the amount was
        // edited in the dashboard — from confirming a payout that never happened.
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['C3' => 4000, 'E3' => self::PAID]));

        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
        self::assertStringContainsString('amount mismatch', implode('', $this->flashErrors()));
    }

    public function testAnIdThatIsNotInTheDatabaseIsReportedAndSkipped(): void
    {
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['A3' => 999999, 'E3' => self::PAID]));

        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
        self::assertStringContainsString('not found', implode('', $this->flashErrors()));
    }

    public function testARowWithNoIdIsReportedAndSkipped(): void
    {
        $transaction = $this->newTransaction(5000);

        // A row the delegate typed in below the list: a status but no id.
        $this->upload($this->delegateFile([$transaction], [
            'E3' => self::PAID,
            'B4' => 'Someone the delegate added',
            'E4' => self::PAID,
        ]));

        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
        self::assertStringContainsString('Missing id', implode('', $this->flashErrors()));
    }

    public function testARejectedRowDoesNotStopTheRowsAfterIt(): void
    {
        // Each row is validated and written in the same pass, so a bad row in the middle
        // must not abort the rest of a school's payouts.
        $stale = $this->newTransaction(5000);
        $good = $this->newTransaction(7000);

        $this->upload($this->delegateFile([$stale, $good], [
            'C3' => 1,
            'E3' => self::PAID,
            'E4' => self::PAID,
        ]));

        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($stale));
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($good));
    }

    // ---- the layout contract --------------------------------------------------

    public function testTheHeaderAndTheBlankRowBelowItAreNotTreatedAsData(): void
    {
        // The reader skips array keys < 2 and the generator starts writing at row 3. Put a
        // real id in the blank row and it must still be ignored: the skip and the blank row
        // only stay correct together.
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([], [
            'A2' => $transaction->getId(),
            'C2' => 5000,
            'E2' => self::UNPAID,
        ]));

        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
    }

    public function testTheStatusColumnIsColumnE(): void
    {
        // Guards against the generator and the reader drifting apart: a status written one
        // column early is invisible to the reader, which then cancels the payout.
        $transaction = $this->newTransaction(5000);

        $this->upload($this->delegateFile([$transaction], ['D3' => self::PAID]));

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    // ---- how it differs from the dashboard -----------------------------------

    public function testAnAlreadyFinishedTransactionIsOverwrittenWithoutComplaint(): void
    {
        // The dashboard's updateStatus() refuses to touch a CONFIRMED/CANCELLED/EXPIRED/PAID
        // transaction (LOCKED_STATUSES). This path calls the service's updateField() instead,
        // which goes straight to a DQL UPDATE and enforces nothing — so re-uploading an old
        // list silently rewrites payouts that were already settled.
        $transaction = $this->newTransaction(5000, Transaction::STATUS_PAID);

        $this->upload($this->delegateFile([$transaction], ['E3' => self::UNPAID]));

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    public function testAnEmptyListIsAccepted(): void
    {
        // A school with nothing to pay still gets a file; uploading it back must be a no-op
        // rather than an error.
        $untouched = $this->newTransaction(5000);

        $response = $this->upload($this->delegateFile([], []));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($untouched));
    }

    // ---- the uploaded filename -------------------------------------------------

    public function testTheUploadedFilenameCannotSteerTheWriteOutOfTheDataDirectory(): void
    {
        // The name comes from whoever posts the file and is concatenated straight into the
        // move target, so it is reduced to a bare filename first. The list still has to be
        // read and applied afterwards — sanitising must not break the round trip.
        $transaction = $this->newTransaction(5000);
        $this->written[] = DATA_PATH . '/pwned.xlsx';

        $this->upload(
            $this->delegateFile([$transaction], ['E3' => self::PAID]),
            '../../public/assets/pwned.xlsx',
        );

        // DATA_PATH . '/../../public/assets/pwned.xlsx' resolves above the application root,
        // which is where the unsanitised concatenation would have put it.
        self::assertFileDoesNotExist(dirname(APP_PATH) . '/public/assets/pwned.xlsx');
        self::assertFileExists(DATA_PATH . '/pwned.xlsx');
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
    }

    public function testAFilenameWithNothingUsableInItStillReadsAsASpreadsheet(): void
    {
        // Without the fallback the move target is DATA_PATH itself (a directory), and the
        // upload dies before a single row is read.
        $transaction = $this->newTransaction(5000);
        $this->written[] = DATA_PATH . '/upload.xlsx';

        $this->upload($this->delegateFile([$transaction], ['E3' => self::PAID]), '../');

        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
    }

    public function testAnUppercaseExtensionStillPicksTheXlsxReader(): void
    {
        // Excel on Windows hands back .XLSX; a case-sensitive check would send it to the
        // Xls reader, which cannot open it.
        $transaction = $this->newTransaction(5000);
        $this->written[] = DATA_PATH . '/LISTA.XLSX';

        $this->upload($this->delegateFile([$transaction], ['E3' => self::PAID]), 'LISTA.XLSX');

        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
    }

    public function testItRedirectsBackToTheUploadForm(): void
    {
        $response = $this->upload($this->delegateFile([$this->newTransaction(5000)], ['E3' => self::PAID]));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringEndsWith('/transaction/uploadTransactionListForm/', $response->getHeaderLine('Location'));
    }

    // ---- fixtures -------------------------------------------------------------

    /** One project/period/donor per test; a fresh beneficiary per transaction. */
    private function newTransaction(int $amount, int $status = Transaction::STATUS_NEW): Transaction
    {
        $this->project ??= $this->createProject();
        $this->period ??= $this->createPeriod($this->project);
        $this->donor ??= $this->createDonor();

        return $this->createTransaction(
            $this->donor,
            $this->createBeneficiary('Beneficiary ' . $amount),
            $this->project,
            $this->period,
            $amount,
            $status,
        );
    }

    /**
     * The file as it comes back from a delegate: produced by the real generator, then
     * edited cell by cell the way Excel would have.
     *
     * @param Transaction[]        $transactions rows to put in the list, in order
     * @param array<string, mixed> $cells        coordinate => value, applied after generation
     *
     * @return string path to the file to upload
     */
    private function delegateFile(array $transactions, array $cells): string
    {
        // getTransactionsBySchool() hands the generator raw rows, not entities — it only
        // ever reads these four properties.
        $rows = array_map(static fn (Transaction $t): object => (object) [
            'id' => $t->getId(),
            'name' => $t->beneficiary->name,
            'amount' => $t->amount,
            'accountNumber' => '000000000000000098',
        ], $transactions);

        $generated = $this->service()->compileXlsxTransactionList($rows, 'Test School');
        $this->written[] = $generated;

        $spreadsheet = IOFactory::load($generated);
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($cells as $coordinate => $value) {
            $sheet->getCell($coordinate)->setValue($value);
        }

        // A separate file, because moveTo() renames the source out from under itself.
        $source = sys_get_temp_dir() . '/solidarity-upload-' . uniqid('', true) . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($source);
        $this->written[] = $source;

        return $source;
    }

    /**
     * @param string|null $clientFilename a hostile name to exercise the sanitiser with; when
     *                                    given, the caller tracks the destination itself,
     *                                    since the whole point is that it is not this name
     */
    private function upload(string $sourcePath, ?string $clientFilename = null): ResponseInterface
    {
        // The controller moves the upload into DATA_PATH and reads it back from there, so
        // the destination is a real file that needs cleaning up.
        if ($clientFilename === null) {
            $clientFilename = 'delegate-list-' . uniqid('', true) . '.xlsx';
            $this->written[] = DATA_PATH . '/' . $clientFilename;
        }

        $request = (new ServerRequest('POST', '/transaction/uploadTransactionList/'))
            ->withUploadedFiles([
                'file' => new UploadedFile(
                    $sourcePath,
                    filesize($sourcePath) ?: null,
                    UPLOAD_ERR_OK,
                    $clientFilename,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ]);

        $controller = $this->controller();
        $controller->setRequest($request);

        return $controller->uploadTransactionList();
    }

    /**
     * updateField() issues a DQL UPDATE, which never reaches the identity map — the entity
     * in memory keeps its old status. Read the column back instead of re-fetching.
     */
    private function statusOf(Transaction $transaction): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            'SELECT status FROM `transaction` WHERE id = ?',
            [$transaction->getId()],
        );
    }

}
