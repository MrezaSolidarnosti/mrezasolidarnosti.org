<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * The payout list sent to delegates — and read back from them.
 *
 * A delegate opens this in Excel, pays the people in it, marks each row Plaćeno or
 * Neplaćeno and returns the file; TransactionController::uploadTransactionList() then
 * parses it and writes transaction statuses straight from the cells. So this is a
 * round-trip format with a parser on the other end, and the column positions are a
 * contract between two pieces of code that never reference each other.
 *
 * The generator currently has no caller — sendTransactionListToAffectedDelegates() is
 * gone from TransactionController — but the parser is still wired up, and the files in
 * data/ show the flow has run for real.
 */
#[CoversClass(TransactionService::class)]
final class XlsxTransactionListTest extends TestCase
{
    /** @var string[] */
    private array $written = [];

    protected function setUp(): void
    {
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
    }

    public function testItWritesTheFileWhereItSaysItDoes(): void
    {
        $path = $this->compile([$this->row(1, 'Petar Petrović', 5000, '000999999999999180')], 'Skola');

        self::assertSame(DATA_PATH . '/lists/Skola.xlsx', $path);
        self::assertFileExists($path);
    }

    // ---- the spreadsheet itself ---------------------------------------------

    public function testTheHeaderRowNamesTheColumnsTheDelegateNeeds(): void
    {
        $sheet = $this->read($this->compile([], 'Skola'));

        self::assertSame('#', $sheet->getCell('A1')->getValue());
        self::assertSame('Ime oštećenog', $sheet->getCell('B1')->getValue());
        self::assertSame('Iznos', $sheet->getCell('C1')->getValue());
        self::assertSame('Broj računa', $sheet->getCell('D1')->getValue());
        self::assertSame('Izaberi status', $sheet->getCell('E1')->getValue());
    }

    public function testEachTransactionBecomesARow(): void
    {
        $sheet = $this->read($this->compile([
            $this->row(11, 'Petar Petrović', 5000, '000999999999999180'),
            $this->row(12, 'Ana Anić', 12000, '000999999999999277'),
        ], 'Skola'));

        self::assertSame(11, $sheet->getCell('A3')->getValue());
        self::assertSame('Petar Petrović', $sheet->getCell('B3')->getValue());
        self::assertSame(5000, $sheet->getCell('C3')->getValue());

        self::assertSame(12, $sheet->getCell('A4')->getValue());
        self::assertSame('Ana Anić', $sheet->getCell('B4')->getValue());
        self::assertSame(12000, $sheet->getCell('C4')->getValue());
    }

    public function testRowTwoIsLeftEmptyBecauseTheReaderSkipsIt(): void
    {
        // Not an off-by-one: the counter starts at 2 and increments before writing, so
        // data begins on row 3 — and uploadTransactionList() skips array keys 0 and 1
        // when reading the file back. The blank row and the skip have to move together.
        $sheet = $this->read($this->compile([$this->row(1, 'Petar', 5000, '123')], 'Skola'));

        self::assertNull($sheet->getCell('A2')->getValue());
        self::assertNotNull($sheet->getCell('A3')->getValue());
    }

    public function testTheAccountNumberKeepsItsTrailingSpace(): void
    {
        // The space forces Excel to treat an 18-digit account as text; without it the
        // number is reformatted into scientific notation and becomes unusable for payment.
        // The parser never reads column D back, so this is purely for the human.
        $sheet = $this->read($this->compile([$this->row(1, 'Petar', 5000, '000999999999999180')], 'Skola'));

        self::assertSame('000999999999999180 ', $sheet->getCell('D3')->getValue());
    }

    public function testTheStatusColumnOffersOnlyPaidOrUnpaidAndRefusesBlanks(): void
    {
        // uploadTransactionList() switches on this cell and its default arm is
        // STATUS_CANCELLED — so a blank or misspelled status silently cancels a payout
        // rather than failing. The dropdown, and allowBlank=false in particular, is the
        // only thing standing between a distracted delegate and a cancelled transaction.
        $sheet = $this->read($this->compile([$this->row(1, 'Petar', 5000, '123')], 'Skola'));

        $validation = $sheet->getCell('E3')->getDataValidation();
        self::assertSame('"Plaćeno,Neplaćeno"', $validation->getFormula1());
        self::assertFalse($validation->getAllowBlank());
    }

    public function testTheRowsLineUpWithTheColumnsTheUploaderReads(): void
    {
        // The round trip, asserted the way uploadTransactionList() actually parses:
        // toArray(), skip keys < 2, then read [0] as the id and [2] as the amount. The
        // amount is compared against the stored transaction and a mismatch rejects the
        // row, so a shifted column here would reject an entire school's list.
        $rows = $this->read($this->compile([
            $this->row(11, 'Petar Petrović', 5000, '000999999999999180'),
            $this->row(12, 'Ana Anić', 12000, '000999999999999277'),
        ], 'Skola'))->toArray();

        $data = array_values(array_filter($rows, static fn ($key) => $key >= 2, ARRAY_FILTER_USE_KEY));

        self::assertCount(2, $data);
        // Strings, not ints: toArray() formats by default ($formatData = true), unlike
        // getCell()->getValue() above. So the id the uploader passes to getById() and the
        // amount it compares against transaction->amount both arrive as text — which is why
        // that comparison is a loose != and not a ===.
        self::assertSame('11', $data[0][0]);
        self::assertSame('5000', $data[0][2]);
        self::assertSame('12', $data[1][0]);
        self::assertSame('12000', $data[1][2]);
        // Column E is left for the delegate to fill in.
        self::assertNull($data[0][4]);
    }

    public function testAnEmptyListStillProducesAUsableFile(): void
    {
        // A school with nothing to pay this round still gets an attachment; a missing file
        // would surface as a mailer error rather than an empty list.
        $sheet = $this->read($this->compile([], 'Skola'));

        self::assertSame('#', $sheet->getCell('A1')->getValue());
        self::assertNull($sheet->getCell('A3')->getValue());
    }

    // ---- the filename --------------------------------------------------------

    public function testSpacesAreStrippedFromTheFilename(): void
    {
        // School names are multi-word; the space removal is what keeps the emailed
        // attachment name from being mangled by mail clients.
        $path = $this->compile([], 'Osnovna skola Novak Radonic');

        self::assertSame(DATA_PATH . '/lists/OsnovnaskolaNovakRadonic.xlsx', $path);
    }

    public function testSerbianDiacriticsSurviveInTheFilename(): void
    {
        // Guards the character filter against being tightened to plain [A-Za-z0-9]: the
        // files already in data/lists are named this way.
        $path = $this->compile([], 'Biblioteka Vlada Aksentijević');

        self::assertSame(DATA_PATH . '/lists/BibliotekaVladaAksentijević.xlsx', $path);
    }

    public function testASchoolNameCannotSteerTheWriteOutOfTheListsDirectory(): void
    {
        // A school name is admin-entered, not public input, so this is a guard rather
        // than a live hole — but the name reaches a filesystem path, and nothing else
        // between the two would stop a slash.
        $path = $this->compile([], '../../public/assets/pwned');

        self::assertSame(DATA_PATH . '/lists', dirname($path));
        self::assertFileDoesNotExist(DATA_PATH . '/../../public/assets/pwned.xlsx');
    }

    public function testANameWithNothingUsableInItFallsBackRatherThanWritingAHiddenFile(): void
    {
        // Without the fallback this writes "/lists/.xlsx" — a dotfile that no delegate
        // would ever find and that the next such school would overwrite.
        self::assertSame(DATA_PATH . '/lists/lista.xlsx', $this->compile([], '...'));
        self::assertSame(DATA_PATH . '/lists/lista.xlsx', $this->compile([], ''));
    }

    // ---- helpers ------------------------------------------------------------

    private function row(int $id, string $name, int $amount, string $accountNumber): object
    {
        // getTransactionsBySchool() returns raw rows, not Transaction entities — the
        // method only ever reads these four properties.
        return (object) compact('id', 'name', 'amount', 'accountNumber');
    }

    /** @param object[] $transactions */
    private function compile(array $transactions, string $school): string
    {
        $path = $this->service()->compileXlsxTransactionList($transactions, $school);
        $this->written[] = $path;

        return $path;
    }

    private function read(string $path): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        return IOFactory::load($path)->getActiveSheet();
    }

    private function service(): TransactionService
    {
        // Every dependency is unused by compileXlsxTransactionList, so none of them are real.
        return new TransactionService(
            $this->createStub(TransactionRepository::class),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(TransactionFilter::class),
            $this->createStub(ProjectService::class),
            $this->createStub(BeneficiaryRepository::class),
            $this->createStub(PeriodRepository::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
