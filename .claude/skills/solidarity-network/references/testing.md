# Testing

The project has a two-layer PHPUnit suite. **There is no local PHP** — PHP 8.4, Composer, and PHPUnit all run inside the **Vagrant box** (`bootstrap.sh` installs `php8.4-*`). Run everything via `vagrant ssh` or give the user the command to run inside the box; never invoke `php`/`composer`/`vendor/bin/phpunit` on the Windows host.

## Stack & layout

- **PHPUnit 13** (`^13.0` in `require-dev`) — requires PHP 8.4+ (the Vagrant box has it; the Docker `php:8.3-fpm` image does **not**, so PHPUnit 13 only runs under Vagrant).
- `phpunit.xml` at repo root. `failOnWarning="true"`. Coverage source = `packages/`.
- `autoload-dev` maps `Solidarity\Tests\` → `tests/`.
- Two test suites:
  - `Unit` → `tests/Unit/` — pure logic, no DB.
  - `Integration` → `tests/Integration/` — real MariaDB.
- Composer scripts (all set `XDEBUG_MODE=coverage` where needed; xdebug is on the box):
  - `composer test` — run the suite.
  - `composer test:coverage` — HTML report → `data/coverage/`.
  - `composer test:gate` — generate `data/coverage/clover.xml` and fail if line coverage is below the threshold (`coverage-check`, currently `50`). Tune the number in `composer.json` after seeing the real figure. `data/coverage/` is gitignored.

## Running

```bash
cd /vagrant
vendor/bin/phpunit                          # both suites
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
composer dump-autoload && vendor/bin/phpunit # after adding new test files
```

## Unit tests (`tests/Unit/`)

Cover entity pure logic, validators, filters, and services whose collaborators can be mocked (e.g. `TransactionService::updateStatus` with a mocked repo). Build entities with `new` + `ArrayCollection`.

- **Use `createStub()`, not `createMock()`, when you only stub return values** (no `->expects()`). PHPUnit 13 raises a "no expectations configured" notice for unused mocks; stubs don't. Use `createMock()` only when verifying interactions via `->expects()`.
- **`Volnix\CSRF\CSRF::validate()` is static** and cannot be stubbed on a mock. Use the test stubs `Solidarity\Tests\Stub\CsrfTrueStub` / `CsrfFalseStub` (subclasses that override the static method) — see `tests/Stub/`.

## Integration harness (`tests/Integration/IntegrationTestCase.php`)

Extend `IntegrationTestCase`. It:
- Reads `config/config-local.php` DB creds but connects to a **separate `solid_test` database** (created if missing) so dev data is never touched.
- Builds the schema once per process via Doctrine `SchemaTool` (drop + create over all mapped entities).
- Wraps **each test in a transaction that is rolled back in `tearDown()`** — `setNestTransactionsWithSavepoints(true)` lets Doctrine's per-flush transactions nest as savepoints. No commits → no fsync, no truncation, instant cleanup, full isolation. This is what makes the suite fast (~12s vs ~64s with truncation).

### Entity-builder helpers (on the base class)

`createProject`, `createPeriod`, `createDonor`, `createBeneficiary` (optional `school`/`createdBy`), `createTransaction`, `createDelegate`, `createCity`, `createSchool`, `createSchoolType(int $id, ...)`, `createDonorPaymentMethod`, `createBeneficiaryPaymentMethod`, `createRegisteredPeriod`, `createPage`, `linkDonorToProject`, `backdateTransaction`. Each persists + flushes and returns the managed entity.

**Builders must be idempotent where a UNIQUE constraint exists.** `createCity()` reuses an existing `'Test City'` rather than inserting a second one. This is not a nicety: a duplicate-key error inside a flush **closes the EntityManager**, and every subsequent test in the process dies with "The EntityManager is closed" — one bad fixture produced 114 failures. `setUp()` also rebuilds the EM if it finds it closed, so a failure stays local to the test that caused it.

**New entity types need registering in two places** — the entity path list in `IntegrationTestCase` (missing paths mean a missing table, e.g. `page`, `email_list`, and the skeletor `Translator`), and `composer dump-autoload` after adding the test file.

### Frontend action tests (`tests/Integration/Frontend/FrontendActionTestCase.php`)

Base class for the donor-facing AJAX actions. Seeds `$_SESSION`/`$_SERVER`, exposes stubbed `logger()/config()/engine()/navigation()/socialLinks()/session()`, and provides `post()`, `forgedPost()` (bad CSRF), `emptyResponse()`, `decode()`, `errorsFrom()` and `redirectPath()`.

- `FLASH_KEY = 'flash_messages'` — SimpleFlash writes raw `$_SESSION`, there is no service to stub.
- **`redirectPath()` exists because `Html::redirect()` prefixes the configured base URL.** Asserting on the raw `Location` header couples the test to `BASE_URL`.

## Gotchas (learned the hard way)

- **Timestampable `createdAt`/`updatedAt` are `insertable: false`** (set by MariaDB `DEFAULT CURRENT_TIMESTAMP`; `updatedAt` uses MySQL-specific `columnDefinition`). Consequences:
  - The harness **must run on MariaDB, not SQLite** (SchemaTool would emit the MySQL DDL verbatim).
  - Doctrine won't persist a `createdAt` you set on the entity. To test the **30-day "monthly" window** in `getPaidSumAmountForDonorPerProject`, back-date with raw SQL via `backdateTransaction($trx, '2020-01-01 00:00:00')`.
- **MSP donor-choice logic keys on `beneficiary->school->type->id === 9 || === 17`** (university types). Auto-increment can't reliably produce those ids (and rollback doesn't reset the counter), so `createSchoolType(int $id, ...)` does a raw `INSERT` with an explicit id.
- **A mod97-valid Serbian account number**: 16 zeros + `'98'` (`'000000000000000098'`). All-zero base → control digit 98. 18 zeros is invalid (control should be `98`, not `00`). Bank prefixes `840` (budget), `150` (Eurobank), `360` (MTS) are rejected by `BeneficiaryValidator`.
- **Private methods you need to test** (e.g. `Statistics::getStats()`): call via `ReflectionMethod` (PHP 8.1+ needs no `setAccessible`). For the `Statistics` constructor's session reads, pass a real pre-seeded `Laminas\Session\Storage\ArrayStorage` (stubbing `StorageInterface` triggers a `Serializable` deprecation).
- **Mocking Doctrine `Query` is impractical** (`final`). Test DB-touching validator/repository paths in the integration suite, not with mocked EMs.
- **`createdAt must not be accessed before initialization`** — the same `insertable: false` mapping as above. The entity you just persisted has no timestamp until you re-read it, so a helper that clears the EM and re-fetches is needed before asserting on one.
- **`with()` is silently ignored without `expects()`.** `$mock->method('x')->with(7)` asserts nothing — the argument constraint only binds when the call is `$mock->expects(self::once())->method('x')->with(7)`. Easy to write, impossible to notice, and the test passes either way.
- **A stubbed collaborator in a service constructor fails somewhere unrecognisable.** Building `DonorService` (14 arguments) for the frontend actions, three separate stubs turned out to be on live paths, and none of them looked like a wiring problem when they broke: a stubbed `ProjectService` returned null for the submitted project, which the allocator reported as a **`NoNeedsException`**; a stubbed `TransactionFilter` returned nothing from `filter()`, so the payload reached `TransactionFactory` empty and surfaced as **"Undefined array key amount"** three layers down; a stubbed `Translator` returns `''` and blanks every status label. Stub only what the path under test genuinely never reaches, and when a domain error appears in a test you just wired up, suspect the wiring before the domain. `FrontendActionTestCase::realDonorService()` is the worked example.
- **`CSRF::validate()` regenerates the token whenever it succeeds** (`vendor/volnix/csrf`), so a token is good for exactly one request. Two successful POSTs in one test must carry the token the previous response returned — `FrontendActionTestCase::postWithToken()` — and the same is true of the real dashboard, which has to read the new token out of each response rather than reusing the one rendered into the page.
- **`createStub` vs `createMock`** is enforced by `failOnWarning`: a `createMock` with no `expects()` raises a "no expectations" notice. Rule of thumb — stub when you're supplying values, mock when you're asserting calls.
- **`TestCase::run()` is final.** A helper method named `run()` is a fatal error, not a test failure; pick another name.
- **Ordering across two doubles** can't be expressed with `expects()->after()`. Have both doubles append to one array on the test case and assert the array (see `DelegateSchoolDiffTest`).
- **`tests/bootstrap.php` must define the constants the app expects** — `APP_PATH`, `DATA_PATH`, `FRONT_ASSET_URL`, `ADMIN_ASSET_URL`, each guarded with `defined() ||`. Code paths that build asset URLs or file paths (the email theme, the XLSX writer) fatal without them.
- **Stubbed `Translator` returns `''`**, so any label built through it vanishes and the assertion fails on an empty string with no clue why. Always `->willReturnArgument(0)`.
- **`phpunit.xml` has `displayDetailsOnPhpunitDeprecations` and `displayDetailsOnPhpunitNotices` on.** With `failOnWarning="true"` a warning is a failure, so keep the details visible or you get a red suite with no message.
- **Fixture donors interfere with each other.** Shared-budget logic means a transaction booked on donor X consumes the budget a later scenario expects X to still have. Give each scenario its own donor rather than reusing one (this is what broke fixture scenario S1 — a history transaction was booked on the donor under test).
- **Tests that write real files** (the XLSX list writes into `data/lists/`, a real application directory) must track and `unlink()` what they created in `tearDown`.
- **Traits need `#[CoversTrait]`**, not `#[CoversClass]` (e.g. `LocalePreferenceTrait`).

## What is and isn't covered

Covered: the allocation/money math (`CreateBalancedForDonor`, `CreateInstructionLadder`, the `Transaction` constraint arithmetic), the expire cron, the donor frontend actions (`Login`, `Register`, `VerifyEmail`, `ConfirmPayment`, `UpdateProfileData`, `UpdateDonationData`, `GetInstructions`), locale handling, `Session`/`Environment`, beneficiary reassignment and table scoping, the delegate school-diff, **both halves of the delegate payout XLSX** (`XlsxTransactionListTest` writes it, `UploadTransactionListTest` reads it back through the controller against a real DB), **`Mailer` send routing**, validators/filters/factories, and the Statistics dashboard.

`Mailer` takes an **optional `?callable $smtpFactory`** fourth constructor argument. It exists only so `catchViaSmtp()` can be observed without opening a socket; it is typed `callable` rather than `Closure` because PHP-DI would try to resolve a class type-hint out of the container, and the service is autowired. Production passes nothing and gets a real `PHPMailer`.

`TransactionController` has its own base class, `tests/Integration/Backend/TransactionControllerTestCase.php` — it builds the eleven-argument constructor once. Only the `TransactionService` is real; the four lookup services are stubbed with an **id-insensitive `getById`**, so a test wanting a different project/period must swap the stub *and* the query id (see `PaymentMethodPreviewTest::preview()`), or it will silently assert against the default. Its Plates `Engine` registers a pass-through `t` function, because `Controller::translate()` goes through `make('t')->t()` and a bare Engine throws.

The backend controllers are covered where they can be driven end to end: the endpoints that write JSON or redirect (`updateStatus`, `updateStatusBulk`, `getPaymentMethodPreview`, `uploadTransactionList`, both `delete`/`deleteBulk` erasure paths, `SchoolController::form()`'s delegate guard). **`form()`'s success path cannot be**: it falls through to `parent::form()` → `Controller::respond()`, which swallows a template failure into a `var_dump` — and with `beStrictAboutOutputDuringTests` that fails the run with no message. Logic worth testing inside a `form()` should either be reached by reflection (as `SchoolControllerTest` does with `getSchoolStatsByPeriod`) or extracted.

**Don't render the admin theme in tests — extract the logic instead.** `Controller::respond()` swallows a template failure into a `var_dump`, which `beStrictAboutOutputDuringTests` turns into an unattributable failure, so backend `form()` success paths look untestable. Standing up a real Plates engine over `themes/admin` was tried and **abandoned**: under `composer test:coverage` it killed the PHP process outright (`Premature end of PHP process`) rather than failing, and the cause was not diagnosable from the test output. The working pattern is `BeneficiaryController`, where the list building lives in `editableProjects()` / `editablePeriods()` / `confirmedAmountsFor()` and `form()` only composes them — `BeneficiaryFormTest` calls those by reflection and never renders. Do the same for any other `form()` worth covering. The **frontend** `Html::respond()` has no such catch — it propagates, so frontend renders need no special handling.

**Frontend actions are tested at the action layer, not only through their services.** `tests/Integration/Frontend/` covers what a browser can actually POST — the login gate, the CSRF check, the JSON envelope and the redirect — while `tests/Integration/Donor/` covers the arithmetic beneath. `FrontendActionTestCase::realDonorService()` builds the 14-argument `DonorService` once, with real repositories and a pass-through `Translator` (a stub returning null blanks every status label).

Not yet covered, in rough order of value:

- `BeneficiaryController::form()` — the registered-period/project dropdown merge (gotcha #11 in `patterns.md`), unreachable without extracting it from `form()`.
- The remaining CRUD controllers — thin, mostly `form()` overrides.
- `MigrateLegacy`, `RecoverInstructions`, `RecoverSfTransactions` — one-shot commands, low value once run.
- `MailerSendMailer::handleApplicationError()` (the Monolog handler) — it reads `$_SERVER['REQUEST_URI']` unguarded and `count($found)` on a possibly-undefined `$found`, so it can fatal *while reporting a fatal*. Framework code, but it is the error-mail path.

## The entity tripwire

`tests/Unit/Core/EntityShapeTest.php` lists every persisted field of every entity and fails when the mapping changes. **That failure is the feature**: adding a column is rarely just a migration, and the rest of the trail lives in other files — the Filter has to pass the key through (they build their arrays by hand, so an unlisted key is silently dropped, which is how a period's `maxAmount` was posted and discarded on every save), the Validator may need a rule, the Factory may need to resolve it, `prepareEntities()` has to emit it and `compileTableColumns()` declare it, and a nullable relation means every reader needs a guard.

Update the list, then walk that trail. Two things the list already records deliberately: `City::$schools`, `Beneficiary\PaymentMethod::$project` and `User::$tenant` have their mappings commented out so they are **not** persisted, and `User::$password` is declared unmapped (magic-link auth; the property only exists to keep `setPassword()` off a dynamic property).

## The other two tripwires

`tests/Integration/Period/PeriodFactoryTest.php` is the **field-coverage** pattern, and the one worth copying to other entities. It declares `WRITABLE` (fields a posted form sets, with values) and `IGNORED` (fields no form writes, with reasons), asserts the two together account for every mapped field, and then runs a real round trip — **starting at `$postData`, through the Filter, into the Factory, out of the database**. Starting at the factory would be useless: the `maxAmount` bug lived in the filter, so the factory never saw the field and a factory-only test passed throughout.

`tests/Unit/Core/RouteTargetTest.php` loads both route tables and asserts every target class exists, is concrete, and is dispatchable (a `Controller`, or invokable when the path carries no `{action}`). It is static by choice — booting the container would catch DI and middleware faults too, but needs Redis, `config-local.php` and `APPLICATION`, which is a lot of setup for a few assertions. It exists because `/educatorImport/` and `/transactionImport/` outlived their controllers and were found by reading: a permission-denied role gets a redirect, but an **admin** gets a 500, since the class is only resolved once the middleware lets the request through.

## Conventions reinforced by the tests

- **Entities initialize their collections in a constructor** (`new ArrayCollection()`), so `new Entity()` is safe for factories. Doctrine bypasses the constructor on hydration, so this only affects app-side instantiation. When adding an entity with `Collection` properties, add the constructor.
- **The cron `CreateTransaction` action passes ids** (`$donor->id`, not the entity) into `TransactionService::create`, matching what the filter/validator/factory expect.
- **Factories resolve relations by id** in `AbstractFactory::formatForWrite` (ManyToOne via `{field}` / `{field}Id`, ManyToMany/OneToMany via arrays of ids). Don't pre-resolve ids to entities before calling the parent factory.
