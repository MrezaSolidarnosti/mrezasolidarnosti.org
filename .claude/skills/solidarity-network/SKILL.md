---
name: solidarity-network
description: >
  Domain knowledge for the Solidarity Network (Mreza solidarnosti) PHP application — a donation management platform
  built on the Skeletor framework. Use this skill whenever working in the C:/radno/solidarity codebase. It covers
  entity relationships, business rules (donation limits, currency conversion, payment matching), the permission/ACL
  system, delegate vs admin access patterns, and project-specific conventions that differ from base Skeletor.
  Trigger on: any work in the solidarity project, mentions of donors/beneficiaries/delegates/transactions in this
  codebase, the donate block / payment instructions / allocation cron, the delegate payout spreadsheet,
  translations (PHP t() or the JS Translator), the PHPUnit suite, statistics pages, payment method matching,
  or permission/route issues.
---

# Solidarity Network Application

A donation management platform ("Mreza solidarnosti protiv represije") connecting **Donors** with **Beneficiaries** (oštećeni — affected/damaged persons) through **Delegates** who manage schools. Built on the Skeletor PHP framework.

**Terminology note:** The UI and some legacy code use "educator" (edukator/oštećeni) interchangeably with "beneficiary". The actual entity class is `Beneficiary`. Routes like `/educator/*` exist but reference the same concept. When you see "educator" in the codebase, think "beneficiary".

## Project Structure

Packages-based layout at `C:/radno/solidarity`:

```
packages/
├── Backend/src/
│   ├── Controller/     # CRUD controllers (Donor, Beneficiary, Transaction, School, Delegate, etc.)
│   └── Action/         # Standalone actions (Index, Statistics, CreateTransaction, Logout)
├── Donor/src/          # Donor entity, PaymentMethod, service, repo, filter, validator, factory
├── Beneficiary/src/    # Beneficiary, RegisteredPeriods, PaymentMethod, service, repo
├── Transaction/src/    # Transaction entity, Project entity, services, factory
├── Delegate/src/       # Delegate entity + service (auto-assignment logic)
├── School/src/         # School entity + service
├── Period/src/         # Period entity + service
├── City/src/           # City entity
├── SchoolType/src/     # SchoolType entity
├── Mailer/src/         # MailerSend-based email service
├── User/src/           # Admin user entity
└── Frontend/src/       # Public-facing actions (registration forms, profiles)
```

### Frontend Actions (`packages/Frontend/src/Action/`)
Public-facing registration and profile pages:
- `Donor.php` — Donor registration form
- `Delegate.php` — Delegate signup with school type/school mapping
- `Educator.php` — Beneficiary ("oštećeni") signup with school mapping
- `ProfileDelegate.php` — Delegate profile management
- `ThankYouDonor.php`, `ThankYouDelegate.php`, `ThankYouEducator.php` — Confirmation pages

### Configuration Files
- `config/config.php` — Base URL (`solid.djavolak.info`), mailer settings, `ignoreTrailingSlash => true`
- `config/config-local.php` — DB credentials, Redis, environment overrides (gitignored)
- `config/constants.php` — APP_PATH, PUBLIC_PATH, DATA_PATH, ADMIN_ASSET_URL
- `config/bootstrap.php` — DI container (PHP-DI), Doctrine ORM, Redis sessions, Monolog logging
- `config/backend/routes.php`, `acl.php`, `permissions.php` — Backend routing and auth

### DI Container Highlights (`config/bootstrap.php`)
- Redis-backed sessions via `Laminas\Session`
- Doctrine ORM with attribute metadata from 7 entity paths
- Custom DQL functions: `DATE()`, `YEAR()`
- Monolog with debug/error file streams + email handler for production
- Multi-entity auth via `EntityRegistry` (user + delegate repositories)

### JavaScript Page Classes (`public/assets/backend/js/pages/`)
- `Transaction.js` — Payment method preview, bulk status updates, project-period filtering
- `Statistics.js` — Tab switching for statistics dashboard
- `beneficiary/Beneficiary.js` — Registered projects and payment methods management
- `beneficiary/PaymentMethods.js` — Dynamic payment method form (bank account vs wire instructions)
- `beneficiary/RegisteredProjects.js` — Add/delete period registrations, duplicate prevention
- `donor/Donor.js` — Donor CRUD with payment methods
- `donor/PaymentMethods.js` — Donor payment method form (project, type, monthly, amount, currency)
- `Delegate.js`, `City.js`, `School.js`, `SchoolType.js`, `User.js` — Standard CRUD pages

## Entity Relationships

Read `references/entities.md` for full entity details, field types, and status constants.

**Core data flow:**
```
Donor → PaymentMethod (per project: amount, monthly, currency, type)
Beneficiary → RegisteredPeriods (amount per period+project)
Beneficiary → School → Delegate
Transaction: Donor → Beneficiary (amount, status, paymentType, project, period)
```

**Key relationship:** Donors and Beneficiaries each have PaymentMethods. A transaction can only be created when their payment types match (bank transfer ↔ bank transfer, wire ↔ wire, etc.). Use `TransactionFactory::matchPaymentType($donor, $beneficiary)` for this.

## Business Rules

### Donation Limits
- **Per-person limit:** 30,000 RSD max between any donor-beneficiary pair across ALL projects (`Transaction::PER_PERSON_LIMIT`)
- **Beneficiary monthly limit:** 240,000 RSD (`Beneficiary::MONTHLY_LIMIT`)
- **Monthly donations:** When a donor's payment method has `monthly = true`, only transactions from the last 30 days count toward their pledged limit

### Currency
- **EUR_TO_RSD_RATE:** 117.5 (`Transaction::EUR_TO_RSD_RATE`)
- Bank transfer (type=1) amounts are in **RSD**
- All other payment types (wire, Western Union, MoneyGram) amounts are in **EUR** and need conversion via `Transaction::eurToRsd()`
- When calculating pledged amounts: check `$pm->type === PaymentMethod::TYPE_BANK_TRANSFER` to determine currency

### Transaction Amount Calculation
When creating a transaction, the amount is `min()` of three constraints:
1. **Donor leftover:** pledged amount (converted to RSD) minus already donated for that payment type + project
2. **Beneficiary leftover:** period allocation minus already received for that project + period
3. **Per-person leftover:** 30,000 minus already donated to this specific beneficiary

### Key Service Methods
- `Transaction::getPaidSumAmountForDonorPerProject($donor, $project, ?$paymentType)` — sum of allocated transactions
- `Transaction::getSumAmountForBeneficiary($beneficiary, ?$project, ?$period)` — sum received
- `Transaction::getRemainingPerPersonLimit($donor, $beneficiary)` — remaining under 30k cap
- `Transaction::compileXlsxTransactionList()` — generates the delegate payout XLSX. A **round-trip format**: `TransactionController::uploadTransactionList()` parses the returned file and writes transaction statuses from it. Read `references/donation-flow.md` before changing the layout — a blank cell in the status column *cancels* a payout.
- `Transaction::hasUnmetNeeds(?Donor, array $projects, array $paymentTypes)` — donor is optional; narrows by project + payment type. Drives the `NoNeedsException` ladder.
- `TransactionFactory::matchPaymentType($donor, $beneficiary)` — finds matching payment type pair
- `Donor::getDonorsByProject($project)` — active verified donors for a project
- `Beneficiary::getByPeriod($periodId)` — beneficiaries registered for a period
- `Beneficiary::fetchTableData()` — applies delegate-based filtering (delegates see only their created beneficiaries)

### Delegate Auto-Assignment
When a delegate is created or updated with school assignments:
- `assignOrphanedBeneficiariesToDelegate($schoolId, $delegateId)` — beneficiaries in that school with no owner (`createdBy_id IS NULL`) get assigned
- When any school is removed: `nullifyCreatedByForDelegate($delegateId)` releases **all** of that delegate's beneficiaries — it is scoped to the delegate, not to the school that triggered it
- Because of that asymmetry, `Delegate::update()` reclaims the **whole new school list** (not just the additions) whenever a removal happened, otherwise removing one school of two silently orphans the other. Without a removal it reclaims only the additions, so an unrelated edit can't sweep up beneficiaries an admin deliberately unassigned. See `DelegateSchoolDiffTest`.
- `sendRoundStartMail` is a UI checkbox, not a column — `update()` unsets it and sets `formLinkSent = 1` instead
- Delegates can only see their own account in the delegate list (`fetchTableData` override)

### Mailer Service (`packages/Mailer/src/Service/Mailer.php`)
MailerSend in production, SMTP (Mailpit) everywhere else — the guard is `Mailer::send()`, covered by `MailerRoutingTest`. The methods that exist:
- `sendDonorInstructionsMail()` — new payment instructions are waiting
- `sendDonorRegisteredMail()` — registration confirmation with the verification token
- `sendDonorLoginMail()` — donor magic link; builds `/donor/verifyEmail?token=`
- `sendDashboardMagicLinkMail()` — **has no callers and is broken** (passes `magicLinkUrl`; `magicLink.php` reads `loginUrl`). Delegate/admin login goes through Skeletor's `MagicLinkService` → `sendMagicLinkEmail()`, which is correct. See `operations.md`.

**Nothing mails delegates.** `sendTransactionListToDelegate()` and `sendRoundStartMailToDelegate()` are gone along with the rest of the delegate payout round (see `donation-flow.md`). Leftovers to expect: `Delegate` service still injects `Mailer` and never uses it, and `Delegate::update()` still handles a `sendRoundStartMail` key that no template posts — it sets `formLinkSent = 1` and sends nothing.

## Auth & Permissions

### Multi-Entity Login
The app supports two login entity types: **user** (admin/staff) and **delegate**.

**All of it is the framework's now** - one route `/login/{entityType}/{action}[/{token}]` to
`Skeletor\Core\Login\Controller\LoginController`, with the entity type checked against
`EntityRegistry`. Adding an account type is a registry entry in `config/bootstrap.php`, not a
controller. `config/config.php` carries:

```php
'auth' => ['methods' => ['magic_link'], 'default' => 'magic_link', 'twoFactor' => false],
```

which **replaced `loginUrl` / `loginUrls`** - `AuthMiddleware` now derives
`/login/{entityType}/magicLinkForm/` from `auth.default`. Nothing here has a password column,
so the password form, forgot-password and reset all **404 at the controller**; that is why
`themes/admin/login/` only needs `magicLinkForm.php` and `confirmMagicLink.php`.

**`Solidarity\Backend\Controller\LoginController` is constants only, and has to stay.** It
exists solely to translate the framework's messages into Serbian. It is *not* needed for
routing (one `{entityType}` route covers everything) nor for templates (`Controller::respond()`
strips `Controller` from the short class name, so both classes resolve to `themes/admin/login`).
The reason `translate()` is not an alternative: `t()` is registered as the **identity function**
on the backend - the Translator extension is loaded only on the frontend, and only for a
non-default locale (`config/bootstrap.php`, the `$useTranslator` block). Subclass constants are
the only lever. Redeclaring a constant works because the framework reads them through
`static::` - see the skeletor skill for the two bugs that cost.

Session variables (set by Skeletor's LoginService):
- `loggedIn` — entity ID
- `loggedInRole` — role integer (1=ADMIN, 2=STUFF, 10=Delegate)
- `loggedInEntityType` — `'user'` or `'delegate'`
- `loggedInEmail` — email address

### Permission System — Critical
**Unmapped routes are DENIED by default.** The `AuthorizationService::canAccessPath()` returns `false` for any path not in the permissions config. When adding new endpoints (AJAX or otherwise), you MUST add them to:
1. `config/backend/permissions.php` — both the `permissions` array (role mapping) and `routes` array (path → permission)
2. `config/backend/acl.php` — the appropriate role level array

### Delegate Access Pattern
Delegates have limited access (role 10). When giving delegates access to a resource:
1. Add paths to `$delegate` array in `acl.php`
2. Add a view permission (e.g., `'school.view'`) that includes role `10`
3. Map specific routes to the view permission (before any wildcard catch-all)
4. In the controller constructor: `$this->tableViewConfig['createButton'] = false` for delegates
5. Filter data: use `$this->uncountableFilters['delegate'] = $delegateId` (scalar values only — arrays not supported in uncountableFilters)
6. Pass `readOnly` flag to templates, use named params: `new Text(name: 'x', value: $v, label: 'X', readOnly: $readOnly)`
7. Block form access to non-assigned entities with redirect

## App-Specific Patterns

Read `references/patterns.md` for detailed code patterns with examples.

### Routes — No Trailing Slash
`config.php` has `ignoreTrailingSlash => true`. The `WebSkeletor` strips trailing slashes before matching. Define routes WITHOUT trailing slash:
```php
// CORRECT
[['GET'], '/statistics', Statistics::class],
// WRONG — will 404
[['GET'], '/statistics/', Statistics::class],
```

### Action Classes (non-CRUD pages)
For pages that don't need a CRUD table (like statistics), use Action classes extending `Skeletor\Core\Action\Web\Html`. Unlike Controllers, Actions must manually set template globals:
```php
$storage = $session->getStorage();
$this->setGlobalVariable('loggedIn', $storage->offsetGet('loggedIn'));
$this->setGlobalVariable('loggedInEmail', $storage->offsetGet('loggedInEmail'));
$this->setGlobalVariable('loggedInRole', $storage->offsetGet('loggedInRole'));
$this->setGlobalVariable('loggedInEntityType', $storage->offsetGet('loggedInEntityType'));
```

### Template Layout
Use `crudTableLayout` (not `standard`):
```php
$this->layout('layout::crudTableLayout', ['pageTitle' => 'Title', 'data' => $data]);
```
- Do NOT use `$this->start('content')` / `$this->stop()` — the `content` section name is reserved in Plates. Content flows automatically.
- Pass `'data' => $data` to the layout so `$data['jsPage']` is available for JS loading.

### JavaScript Page Classes
`crud.js` loads page classes via `data-page` attribute on `<main>`. Set `jsPage` in template data:
```php
return $this->respond('template/view', ['jsPage' => 'Statistics', ...]);
```
The JS file at `public/assets/backend/js/pages/Statistics.js` must `export default class Statistics` with an `init()` method. Non-CRUD pages don't need to extend `CrudPage`.

### Statistics / Aggregate Queries
Use `EntityManagerInterface` for aggregate DQL queries. Pattern:
```php
$this->em->createQueryBuilder()
    ->select('COALESCE(SUM(t.amount), 0)')
    ->from(Transaction::class, 't')
    ->where('t.status = :status')
    ->setParameter('status', $status);
```
For currency-mixed totals: query RSD (type=1) and EUR (others) separately, then `$rsdTotal + Transaction::eurToRsd($eurTotal)`.

### Adding Statistics Tabs to Forms
Use the `setAdditionalTabContent()` pattern:
```php
$statsTab = (new Tab('Statistika'))->addInputGroup(new InputGroup(width: InputGroupWidth::FULL_WIDTH));
$statsHTML = $this->fetch('/module/statisticsInForm', ['stats' => $data['stats']]);
$formRenderer->setAdditionalTabContent($statsTab, $statsHTML);
$form->addTab($statsTab);
```

### Navigation
Edit `themes/admin/partials/global/navigation.php`. Items use `data-href` attribute. Sections are gated by `$loggedInEntityType`, and within the staff block by `$loggedInRole`:
- `in_array($loggedInEntityType, ['delegate', 'user'])` — shared items
- `$loggedInEntityType === 'user'` — staff and admin (statistics)
- `$loggedInRole === User::ROLE_ADMIN` — reference data (school types, cities), users, newsletter, periods, pages, theme

**The gate must match `permissions.php`.** An item shown to a role whose permission doesn't include it is a link that bounces the user with a no-permissions flash — there is no error, just a menu that doesn't work. `AccessControlTest` asserts the permission side of this matrix; the template has to be kept in step by hand.

## Testing

The project has a PHPUnit 13 suite split into `Unit` (pure logic, no DB) and `Integration` (real MariaDB) suites. **No local PHP — everything runs inside the Vagrant box (PHP 8.4).** Run with `cd /vagrant && vendor/bin/phpunit` (add `composer dump-autoload` after new test files).

Integration tests extend `tests/Integration/IntegrationTestCase.php`, which targets a separate `solid_test` database and wraps each test in a rolled-back transaction (fast, isolated). It provides entity-builder helpers (`createDonor`, `createBeneficiary`, `createTransaction`, etc.). Donor-facing AJAX actions extend `tests/Integration/Frontend/FrontendActionTestCase.php`, which seeds the session/server globals and provides `post()`, `forgedPost()`, `decode()`, `errorsFrom()`, `redirectPath()`.

Read `references/testing.md` for the full harness, helpers, run commands, the coverage map (what is and isn't tested, and why `Mailer` needs a refactor before it can be), and the non-obvious gotchas — `insertable:false` timestamps + the monthly-window backdate trick, static `CSRF::validate` stubs, `createStub` vs `createMock`, `with()` being silently ignored without `expects()`, **a duplicate-key error closing the EntityManager and cascading into every later test**, forcing `SchoolType` ids, mod97-valid account numbers, and reflection for private `getStats()`.

**Conventions the tests rely on / reinforce:**
- Entities **initialize their `Collection` properties in a constructor** (`new ArrayCollection()`) so `new Entity()` is safe for factories. Add this when creating a new entity with collections.
- The cron `CreateTransaction` action passes **ids** (`$donor->id`) into `TransactionService::create`, matching what the filter/validator/factory expect — not entities.
- Factories let `AbstractFactory::formatForWrite` resolve relations by id; don't pre-resolve ids to entities first.

## Operations, apps, auth & migration

The app runs as **two apps off one bootstrap** — `getenv('APPLICATION')` is `frontend` (solidarity.local) or `backend` (solidarityadmin.local), both loading `config/bootstrap.php`. **Donor auth is magic-link** on the frontend (`Donor implements AuthenticatableInterface`, `ROLE_DONOR=20`; actions Register/VerifyEmail/Login). Dev mail is caught by **Mailpit** (http://192.168.25.43:8025) via the `Mailer`'s `APPLICATION_ENV` guard. Cron runs CLI Actions (`deploy/crontab`, `php public/cli.php createTransactions run`). Legacy data comes from the Symfony **solidaritySF** app via `MigrateLegacy` (`php public/cli.php migrateLegacy run|commit`).

Read `references/operations.md` for the details and the load-bearing gotchas: the **backend-only DI block** (auth bindings must be moved out for frontend flows), the **OPcache-needs-FPM-restart** trap after bootstrap edits, the donor magic-link wiring, the Mailpit/MailerSend guard, the cron env requirement, and the legacy entity mapping. It also carries the **open decisions** — questions raised and deliberately left for the user to answer (the one-time path persisting no payment method, `donorConfirmed`, the delegate nullify scoping, `compileXlsxTransactionList` having no caller). Check that list before assuming something half-finished is a bug.

### The urgent allocation round

`php public/cli.php createTransactionsUrgent dry|run [target=<rsd>]` -
`Backend\Action\CreateTransactionUrgent`, a `CreateTransaction` subclass overriding only
`selectDonors()`. The scheduled round orders **least-recently-tried first** (a sweep for who is
still alive); this one inverts it to **most-recently-active first**, for when a specific sum is
needed fast. It is a patch: swap it into `deploy/crontab` while the need lasts, then swap back.

Two things to know before running it:

- **`target=` is an upper bound on pledges, not a forecast.** Selection stops once the chosen
  donors' combined *spendable pledge* reaches it; what actually moves is capped again by unmet
  beneficiary need, the 30,000 per-person limit and payment-type matching, so the real figure
  is always lower - often much lower. `check/donor-activity.sql` is the same query with the
  ranking exposed; use it to pick a number. Omitting `target=` processes the whole pool, which
  is the safer default.
- **Its eligible pool is *smaller* than the scheduled round's, not equal.** Both require
  `isActive = 1`, `status IN (NEW, VERIFIED)` and a pledge to a running project, but the urgent
  query additionally **inner-joins a spendable-pledge subquery**, so a donor with no remainder
  >= 500 RSD never appears. `DonorRepository::getDonorsByProject()` has no such filter - the
  scheduled round walks exhausted donors too and `createBalancedForDonor()` skips them
  internally (one `getPaidSumAmountForDonorPerProject` query per pledge, plus a
  `Processing donor ...` log line, for nothing). That is an absent filter rather than a design
  choice; the one real argument for leaving it is that the scheduled round then keeps **one**
  copy of the budget rule, in the allocator, while the urgent query had to duplicate it (500
  floor, 117.5 EUR->RSD, 30-days-vs-all-time window, the four allocated statuses) to answer
  `target=` up front. If those ever drift apart, the pre-filter selects the wrong donors.

## Git: this repo is a fork - never "sync" it

`origin` is `djavolak/mrezasolidarnosti.org`, `upstream` is
`MrezaSolidarnosti/mrezasolidarnosti.org`. GitHub's fork UI offers **"Discard N commits to make
this branch match the upstream repository"** whenever the branches diverge. That deletes *your*
commits and is never what you want here - the flow is merge `upstream/main`, resolve, push, PR
(the upstream history is a wall of `Merge pull request #NNN from djavolak/main`).

Local `upstream/main` goes stale fast, so `git fetch upstream` before judging any divergence.
The recurring conflict is **`composer.lock`** - both sides bump the `dj_avolak/skeletor`
`reference` and the content hash. Resolve by checking which reference is newer
(`git -C <skeleton> merge-base --is-ancestor <theirs> <yours>`), keep that side, then
`composer update --lock` to regenerate the hash rather than hand-editing.

## The donation flow (donor-facing)

Read `references/donation-flow.md` when touching the donate block, instructions, or allocation. It covers the **mode toggle** (monthly = payment methods for the cron, one-time = an instruction created immediately), the JS contract in `donate/Form.js` and `donate/Donate.js` — including why the action comes from `this.#form.action` and never from `e.submitter.formAction`, which was the original silent-save bug — the `hasUnmetNeeds()`/`NoNeedsException` ladder, the 500 RSD floor and escalating min-slice, the `manual` flag, the **72h expire cron and its must-be-shorter-than-the-cron-gap constraint**, `lastVisit`, the hardcoded MSP=1/MSPR=2 project ids, and the delegate payout XLSX round-trip.

## Translations

Read `references/i18n.md` before touching translated strings. Two systems (PHP `t()` vs the JS `Translator`) that look alike and fail differently — the key is the English string in both, so **an untranslated string looks correct on `/en` and only breaks on the Serbian site**. Also covers the `language`-table row that silently disables every `t()` call, the `skeletortranslations#2#` cache key, `jsInvert`, and the three CLI commands (`resetTranslationsCache`, `exportTranslations`, `importJsTranslations`).
