<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Page\Entity\Page;
use Solidarity\Period\Entity\Period;
use Solidarity\School\Entity\City;
use Solidarity\School\Entity\School;
use Solidarity\School\Entity\SchoolType;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

/**
 * Base class for integration tests that hit a real MariaDB database.
 *
 * Connects with the credentials from config/config-local.php but against a
 * dedicated "<dbname>_test" schema, so dev data is never touched. The schema
 * is (re)built once per process via Doctrine SchemaTool; every test starts
 * from truncated tables.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?EntityManagerInterface $em = null;
    private int $seq = 0;

    public static function setUpBeforeClass(): void
    {
        if (self::$em !== null) {
            return; // schema already built earlier in this process
        }

        $root = dirname(__DIR__, 2);
        $local = require $root . '/config/config-local.php';
        $db = $local['db']['write'];
        $testDbName = $db['name'] . '_test';

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                $root . '/packages/Delegate/src/Entity',
                $root . '/packages/Donor/src/Entity',
                $root . '/packages/Transaction/src/Entity',
                $root . '/packages/Period/src/Entity',
                $root . '/packages/Beneficiary/src/Entity',
                $root . '/packages/School/src/Entity',
                $root . '/packages/User/src/Entity',
                $root . '/packages/EmailList/src/Entity',
                $root . '/packages/Page/src/Entity',
                // Translation/Language: the frontend Translator reads them, and a missing
                // table surfaces as a caught exception rather than a clear error.
                $root . '/vendor/dj_avolak/skeletor/src/Translator',
                $root . '/vendor/dj_avolak/skeletor/src/Image',
                $root . '/vendor/dj_avolak/skeletor/src/Login',
                $root . '/vendor/dj_avolak/skeletor/src/ThemeSettings',
            ],
            isDevMode: true,
        );
        $ormConfig->addCustomStringFunction('DATE', fn () => new \DoctrineExtensions\Query\Mysql\Date('DATE'));
        $ormConfig->addCustomStringFunction('YEAR', fn () => new \DoctrineExtensions\Query\Mysql\Year('YEAR'));
        // Generate entity proxies in-memory (eval) rather than writing them to the shared
        // system temp dir — proxy files there can be owned by the web-server user, and the
        // atomic rename() then fails with "Operation not permitted" during the test run.
        $ormConfig->setAutoGenerateProxyClasses(\Doctrine\ORM\Proxy\ProxyFactory::AUTOGENERATE_EVAL);
        // symfony/var-exporter 8 removed LazyGhostTrait, so Doctrine's proxy factory throws
        // unless native lazy objects are used. Mirrors config/bootstrap.php.
        $ormConfig->enableNativeLazyObjects(true);

        $serverParams = [
            'driver' => 'pdo_mysql',
            'host' => $db['host'],
            'user' => $db['user'],
            'password' => $db['pass'],
        ];

        // Ensure the dedicated test database exists.
        $serverConn = DriverManager::getConnection($serverParams);
        $serverConn->executeStatement(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $testDbName,
        ));
        $serverConn->close();

        $connection = DriverManager::getConnection($serverParams + ['dbname' => $testDbName], $ormConfig);
        // Let Doctrine's per-flush transactions nest inside the per-test
        // transaction (as savepoints) instead of committing to disk.
        $connection->setNestTransactionsWithSavepoints(true);
        self::$em = new EntityManager($connection, $ormConfig);

        $schemaTool = new SchemaTool(self::$em);
        $metadata = self::$em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Each test runs inside a transaction that is rolled back in tearDown, so
     * no data is ever committed: no fsync per flush, no truncation, instant
     * cleanup, and full isolation between tests.
     */
    protected function setUp(): void
    {
        // Doctrine closes the EntityManager when a flush throws, and it is shared by every
        // test in the run — so without this one genuine failure reports as a hundred
        // "The EntityManager is closed" errors and buries its own cause. Rebuilding on the
        // same connection and configuration keeps each failure attributable to its test.
        if (!self::$em->isOpen()) {
            self::$em = new EntityManager(self::$em->getConnection(), self::$em->getConfiguration());
        }

        self::$em->clear();
        self::$em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = self::$em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        self::$em->clear();
    }

    protected function em(): EntityManagerInterface
    {
        return self::$em;
    }

    // ---- Entity builders -------------------------------------------------

    protected function createProject(string $code = 'MSP', string $name = 'Test Project'): Project
    {
        $project = new Project();
        $project->name = $name;
        $project->code = $code;
        $project->logo = '';
        $project->delegates = new ArrayCollection();
        $project->periods = new ArrayCollection();

        self::$em->persist($project);
        self::$em->flush();

        return $project;
    }

    /**
     * Inserts a Project with a forced id. Needed because some queries hardcode
     * project ids (e.g. TransactionRepository::PROJECT_MSP = 1).
     */
    protected function createProjectWithId(int $id, string $code = 'MSP'): Project
    {
        $table = self::$em->getClassMetadata(Project::class)->getTableName();
        self::$em->getConnection()->executeStatement(
            sprintf('INSERT INTO `%s` (id, name, code, logo) VALUES (:id, :name, :code, :logo)', $table),
            ['id' => $id, 'name' => 'Project ' . $id, 'code' => $code, 'logo' => ''],
        );

        return self::$em->find(Project::class, $id);
    }

    protected function createPeriod(
        Project $project,
        int $month = 1,
        int $year = 2026,
        int $maxAmount = 240000,
        string $type = Period::TYPE_FULL,
    ): Period {
        $period = new Period();
        $period->project = $project;
        $period->month = $month;
        $period->year = $year;
        $period->maxAmount = $maxAmount;
        $period->type = $type;
        $period->active = true;
        $period->processing = false;

        self::$em->persist($period);
        self::$em->flush();

        return $period;
    }

    protected function createDonor(
        ?string $email = null,
        int $wantsToDonateTo = Donor::DONATE_TO_ALL,
        int $status = Donor::STATUS_VERIFIED,
    ): Donor {
        $donor = new Donor();
        $donor->email = $email ?? ('donor' . (++$this->seq) . '@example.com');
        $donor->firstName = 'First';
        $donor->lastName = 'Last';
        $donor->status = $status;
        $donor->wantsToDonateTo = $wantsToDonateTo;
        $donor->isActive = '1';
        $donor->comment = null;
        $donor->ipv4 = null;
        $donor->lastLogin = null;
        $donor->paymentMethods = new ArrayCollection();
        $donor->transactions = new ArrayCollection();
        $donor->projects = new ArrayCollection();

        self::$em->persist($donor);
        self::$em->flush();

        return $donor;
    }

    protected function createDelegate(int $status = Delegate::STATUS_VERIFIED): Delegate
    {
        $delegate = new Delegate();
        $delegate->email = 'delegate' . (++$this->seq) . '@example.com';
        $delegate->name = 'Test Delegate';
        $delegate->status = $status;
        $delegate->phone = '060';
        $delegate->verifiedBy = '';
        $delegate->comment = null;
        $delegate->adminComment = null;
        $delegate->ipv4 = null;
        $delegate->lastLogin = null;
        $delegate->schools = new ArrayCollection();
        $delegate->projects = new ArrayCollection();

        self::$em->persist($delegate);
        self::$em->flush();

        return $delegate;
    }

    protected function createBeneficiary(
        string $name = 'Test Beneficiary',
        ?School $school = null,
        int $status = Beneficiary::STATUS_NEW,
        ?Delegate $createdBy = null,
    ): Beneficiary {
        $beneficiary = new Beneficiary();
        $beneficiary->name = $name;
        $beneficiary->status = $status;
        $beneficiary->comment = null;
        $beneficiary->school = $school;
        $beneficiary->createdBy = $createdBy;
        $beneficiary->transactions = new ArrayCollection();
        $beneficiary->registeredPeriods = new ArrayCollection();
        $beneficiary->paymentMethods = new ArrayCollection();

        self::$em->persist($beneficiary);
        self::$em->flush();

        return $beneficiary;
    }

    protected function createTransaction(
        Donor $donor,
        Beneficiary $beneficiary,
        Project $project,
        Period $period,
        int $amount,
        int $status = Transaction::STATUS_NEW,
        int $paymentType = 1,
        int $amountEur = 0,
    ): Transaction {
        $transaction = new Transaction();
        $transaction->donor = $donor;
        $transaction->beneficiary = $beneficiary;
        $transaction->project = $project;
        $transaction->period = $period;
        $transaction->amount = $amount;
        $transaction->amountEur = $amountEur;
        $transaction->status = $status;
        $transaction->paymentType = $paymentType;
        $transaction->accountNumber = null;
        $transaction->instructions = null;
        $transaction->comment = null;
        $transaction->paymentCode = null;

        self::$em->persist($transaction);
        self::$em->flush();

        return $transaction;
    }

    protected function linkDonorToProject(Donor $donor, Project $project): void
    {
        $donor->projects->add($project);
        self::$em->flush();
    }

    protected function createDonorPaymentMethod(
        Donor $donor,
        Project $project,
        int $type = 1,
        bool $monthly = false,
        int $amount = 5000,
        int $currency = 1,
        bool $allocateUntilSpent = false,
    ): DonorPaymentMethod {
        $pm = new DonorPaymentMethod();
        $pm->donor = $donor;
        $pm->project = $project;
        $pm->type = $type;
        $pm->monthly = $monthly ? 1 : 0;
        // Defaults off, like the column: the cron takes monthly pledges plus the legacy
        // lump sums explicitly flagged to drain, and nothing else.
        $pm->allocateUntilSpent = $allocateUntilSpent ? 1 : 0;
        $pm->amount = $amount;
        $pm->currency = $currency;

        self::$em->persist($pm);
        self::$em->flush();

        // Keep the in-memory donor consistent so getPaymentMethodsForProject()
        // (used by the monthly-window logic) sees this method.
        $donor->paymentMethods->add($pm);

        return $pm;
    }

    /**
     * A reusable "any type" for schools whose type is irrelevant to the test.
     *
     * Find-or-create, like createCity(): createSchoolType() inserts with an explicit id, and
     * calling it twice for the same one is a duplicate key — which, inside a flush, closes
     * the EntityManager and fails every remaining test in the process rather than this one.
     */
    protected function defaultSchoolType(): SchoolType
    {
        return self::$em->getRepository(SchoolType::class)->findOneBy(['name' => 'Test Type'])
            ?? $this->createSchoolType(1, 'Test Type');
    }

    /**
     * Inserts a SchoolType with a forced id (the MSP donor-choice logic keys on
     * school->type->id === 9 / 17, which auto-increment can't reliably produce).
     */
    protected function createSchoolType(int $id, string $name = 'Type'): SchoolType
    {
        $table = self::$em->getClassMetadata(SchoolType::class)->getTableName();
        self::$em->getConnection()->executeStatement(
            sprintf('INSERT INTO `%s` (id, name) VALUES (:id, :name)', $table),
            ['id' => $id, 'name' => $name],
        );

        return self::$em->find(SchoolType::class, $id);
    }

    /**
     * city.name is UNIQUE, so a second call with the same name is a 1062 rather than a
     * second city — and because it fails inside a flush it closes the EntityManager and
     * takes the rest of the run with it. Reuse the existing row instead: a test that wants
     * two schools almost always wants them in one city, and one that genuinely needs two
     * cities passes two names.
     */
    /**
     * A published page. translationGroupId links the language variants of one logical
     * page; pass the same value with different $locale/$slug to build a translation pair.
     */
    protected function createPage(
        string $slug,
        ?int $translationGroupId = null,
        string $locale = 'sr',
        int $status = Page::STATUS_PUBLISHED,
    ): Page {
        $page = new Page();
        $page->slug = $slug;
        $page->title = ucfirst($slug);
        $page->description = null;
        $page->blockData = [];
        $page->featuredImage = null;
        $page->languageCode = $locale;
        $page->translationGroupId = $translationGroupId;
        $page->status = $status;
        // From the Seo trait: seoTitle/seoDescription are NOT NULL, and every nullable
        // property here still has to be assigned — they are typed with no default, so
        // Doctrine reading them during flush would fatal on "before initialization".
        $page->seoTitle = ucfirst($slug);
        $page->seoDescription = '';
        $page->seoImage = null;

        self::$em->persist($page);
        self::$em->flush();

        return $page;
    }

    protected function createCity(string $name = 'Test City'): City
    {
        $existing = self::$em->getRepository(City::class)->findOneBy(['name' => $name]);
        if ($existing) {
            return $existing;
        }

        $city = new City();
        $city->name = $name;

        self::$em->persist($city);
        self::$em->flush();

        return $city;
    }

    /**
     * School::$type is required, so a caller that does not care about it gets a shared
     * default rather than a null — most tests only need "a school somewhere".
     */
    protected function createSchool(City $city, ?SchoolType $type = null, string $name = 'Test School'): School
    {
        $school = new School();
        $school->name = $name;
        $school->city = $city;
        $school->type = $type ?? $this->defaultSchoolType();

        self::$em->persist($school);
        self::$em->flush();

        return $school;
    }

    protected function createBeneficiaryPaymentMethod(
        Beneficiary $beneficiary,
        int $type = 1,
        ?string $accountNumber = '160600000027894822',
        ?string $wireInstructions = null,
    ): BeneficiaryPaymentMethod {
        $pm = new BeneficiaryPaymentMethod();
        $pm->beneficiary = $beneficiary;
        $pm->type = $type;
        $pm->accountNumber = $accountNumber;
        $pm->wireInstructions = $wireInstructions;

        self::$em->persist($pm);
        self::$em->flush();

        $beneficiary->paymentMethods->add($pm);

        return $pm;
    }

    protected function createRegisteredPeriod(
        Beneficiary $beneficiary,
        Project $project,
        Period $period,
        int $amount,
    ): RegisteredPeriods {
        $rp = new RegisteredPeriods();
        $rp->beneficiary = $beneficiary;
        $rp->project = $project;
        $rp->period = $period;
        $rp->amount = $amount;

        self::$em->persist($rp);
        self::$em->flush();

        $beneficiary->registeredPeriods->add($rp);

        return $rp;
    }

    /**
     * createdAt is insertable:false (set by the DB default), so it must be
     * back-dated with raw SQL to test the 30-day "monthly" window.
     */
    protected function backdateTransaction(Transaction $transaction, string $datetime): void
    {
        self::$em->getConnection()->executeStatement(
            'UPDATE `transaction` SET createdAt = :dt WHERE id = :id',
            ['dt' => $datetime, 'id' => $transaction->getId()],
        );
    }
}
