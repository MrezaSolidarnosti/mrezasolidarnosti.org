# Code Patterns Reference

## Table of Contents
1. [Adding New Endpoints](#adding-new-endpoints)
2. [Action Classes (Non-CRUD Pages)](#action-classes)
3. [Statistics / Aggregate Queries](#statistics-queries)
4. [Adding Tabs to Forms](#adding-tabs-to-forms)
5. [Delegate Access Control](#delegate-access-control)
6. [Transaction Creation Logic](#transaction-creation-logic)
7. [Currency Handling](#currency-handling)
8. [Navigation Items](#navigation-items)
9. [JavaScript Page Classes](#javascript-page-classes)
10. [Common Gotchas](#common-gotchas)

---

## Adding New Endpoints

Every new endpoint needs three config entries or it will be blocked (denied by default):

### 1. Route (`config/backend/routes.php`)
```php
// Action class (no {action} param)
[['GET'], '/statistics', \Solidarity\Backend\Action\Statistics::class],

// Controller with action dispatch
[['GET', 'POST'], '/school/{action}[/{id}]', \Solidarity\Backend\Controller\SchoolController::class],
```
**No trailing slash** — `ignoreTrailingSlash` strips them before matching.

### 2. Permission (`config/backend/permissions.php`)
```php
// In 'permissions' array — which roles can access
'statistics.view' => [User::ROLE_ADMIN, User::ROLE_STUFF],

// In 'routes' array — path to permission mapping
'/statistics' => 'statistics.view',
```
For controllers with multiple actions, map specific paths BEFORE wildcard:
```php
'/school/view/' => 'school.view',        // specific — checked first
'/school/tableHandler/' => 'school.view',
'/school/form/*' => 'school.view',
'/school/*' => 'school.manage',           // wildcard catch-all — last
```

### 3. ACL (`config/backend/acl.php`)
```php
// $level2 = staff paths
// $level1 = admin (merges with level2)
// $delegate = delegate-specific paths
$delegate = [
    '/school/view/*',
    '/school/tableHandler/*',
    '/school/form/*',
];
```

---

## Action Classes

For pages that render HTML but don't need CRUD table functionality. Extend `Skeletor\Core\Action\Web\Html`.

```php
namespace Solidarity\Backend\Action;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Laminas\Config\Config;
use League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;

class Statistics extends Html
{
    public function __construct(
        Logger $logger, Config $config, Engine $template,
        private EntityManagerInterface $em,
        \Laminas\Session\ManagerInterface $session,
    ) {
        parent::__construct($logger, $config, $template);
        // REQUIRED: set template globals for navigation
        $storage = $session->getStorage();
        $this->setGlobalVariable('loggedIn', $storage->offsetGet('loggedIn'));
        $this->setGlobalVariable('loggedInEmail', $storage->offsetGet('loggedInEmail'));
        $this->setGlobalVariable('loggedInRole', $storage->offsetGet('loggedInRole'));
        $this->setGlobalVariable('loggedInEntityType', $storage->offsetGet('loggedInEntityType'));
    }

    public function __invoke($request, $response)
    {
        // Use crudTableLayout, pass data for jsPage
        return $this->respond('statistics/view', [
            'jsPage' => 'Statistics',
            'myData' => $computedData,
        ]);
    }
}
```

### Template for Action
```php
<?php
$this->layout('layout::crudTableLayout', ['pageTitle' => 'Title', 'data' => $data]);
// Content goes here directly — NO start('content')/stop()
?>
<div>Your HTML here, $data['myData'] is available</div>
```

---

## Statistics Queries

### Counting entities
```php
$qb = $this->em->createQueryBuilder()
    ->select('COUNT(DISTINCT d.id)')
    ->from(Donor::class, 'd')
    ->where('d.status != :deleted')
    ->setParameter('deleted', Donor::STATUS_DELETED);
// Optional project filter via join
if ($project) {
    $qb->innerJoin('d.projects', 'p')
        ->andWhere('p.id = :projectId')
        ->setParameter('projectId', $project->id);
}
return (int) $qb->getQuery()->getSingleScalarResult();
```

### Summing transaction amounts by status
```php
$qb = $this->em->createQueryBuilder()
    ->select('COALESCE(SUM(t.amount), 0)')
    ->from(Transaction::class, 't')
    ->where('t.status = :status')
    ->setParameter('status', Transaction::STATUS_CONFIRMED);
return (int) $qb->getQuery()->getSingleScalarResult();
```

### Per-school statistics (joining through beneficiary)
```php
$qb = $this->em->createQueryBuilder()
    ->select('COALESCE(SUM(t.amount), 0) as total, COUNT(t.id) as cnt')
    ->from(Transaction::class, 't')
    ->innerJoin('t.beneficiary', 'b')
    ->where('b.school = :schoolId')
    ->andWhere('t.period = :periodId')
    ->andWhere('t.status = :status');
$row = $qb->getQuery()->getSingleResult();
```

---

## Adding Tabs to Forms

### In the controller `form()` method
Compute data and add to `$this->formData`:
```php
$this->formData['myStats'] = $this->computeStats($id);
```

### In the template
```php
$formRenderer = new TabbedFormRenderer($form, $data['formTitle']);

if ($data['dataAction'] === 'update' && !empty($data['myStats'])) {
    $statsTab = (new Tab('Statistika'))
        ->addInputGroup((new InputGroup(width: InputGroupWidth::FULL_WIDTH)));
    $statsHTML = $this->fetch('/module/statsPartial', ['stats' => $data['myStats']]);
    $formRenderer->setAdditionalTabContent($statsTab, $statsHTML);
    $form->addTab($statsTab);
}

echo $formRenderer->render();
```

### Partial template (`themes/admin/module/statsPartial.php`)
Use scoped CSS with unique class prefixes to avoid conflicts. Stat cards pattern:
```html
<div class="moduleStatsGrid">
    <div class="moduleStatCard">
        <span class="label">Potvrdene (<?= $count ?>)</span>
        <span class="value"><?= number_format($amount, 0, ',', '.') ?> <span class="unit">RSD</span></span>
    </div>
</div>
```

---

## Delegate Access Control

### Controller constructor
```php
public function __construct(/* ... */, private Delegate $delegate) {
    parent::__construct($service, $session, $config, $flash, $template);
    if ($this->isDelegateSession()) {
        $this->tableViewConfig['createButton'] = false;
    }
}

private function isDelegateSession(): bool {
    return $this->getSession()->getStorage()->offsetGet('loggedInEntityType') === 'delegate';
}
```

### Filtering table data
Use `uncountableFilters` with scalar values (arrays NOT supported):
```php
public function tableHandler() {
    if ($this->isDelegateSession()) {
        // Filter by delegate relationship (scalar — works)
        $this->uncountableFilters['delegate'] = $this->getSession()->getStorage()->offsetGet('loggedIn');
    }
    return parent::tableHandler();
}
```

### Readonly forms
Pass flag from controller: `$this->formData['readOnly'] = $this->isDelegateSession();`

In template, use named parameters:
```php
$readOnly = $data['readOnly'] ?? false;
$name = (new Text(name: 'name', value: $data['model']?->name, label: 'Name', readOnly: $readOnly));
$select = (new Select(name: 'city', optionsCollection: $col, label: 'City', readOnly: $readOnly));
```

### Restricting form access to assigned entities
```php
public function form(): Response {
    if ($this->isDelegateSession() && $id) {
        $allowedIds = $this->getDelegateEntityIds();
        if (!in_array((int) $id, $allowedIds)) {
            return $this->redirect('/module/view/');
        }
    }
    // ...
}
```

---

## Transaction Creation Logic

**Period flags:** `active` = delegates may add beneficiaries to the period; **`processing`** = the period is open for **transaction creation**. Both allocation paths key on `processing` (not `active`).

There are **two allocation paths**, both funnelling through the shared private `allocateToBeneficiary()` (donor school/uni preference, payment-type match, beneficiary period remaining, per-person yearly cap, then `create()`) and both over **`processing`** periods:

1. **Cron / scheduled** — `CreateTransaction` action → `createBalancedForDonor($donor, $projects)`. Each donor's **pledged** `PaymentMethod.amount` (minus paid) is the per-project budget. Round-robin across the donor's pledged projects.
2. **On-demand (donor-triggered)** — frontend `CreateInstruction` → `Donor::createTransaction()` → `createForDonor($donor, $projects, $budgets)`. One-time, initiated by the donor (not scheduled): the donor's chosen projects + per-payment-type RSD `$budgets` (`[type => rsd]`, EUR converted via `Transaction::eurToRsd`). `project === -1` in the form means every project. Returns total RSD allocated; `CreateInstruction` shows "nema potreba…" when it's 0.

The cron loop, conceptually:
```
For each active project with processing periods:
  For each donor with payment methods for project:
    For each matching payment method type:
      Calculate donorLeftover = pledgedRSD - donatedSoFar
      For each beneficiary in period:
        Calculate beneficiaryLeftover = periodAmount - receivedSoFar
        Calculate perPersonLeftover = getRemainingPerPersonLimit()
        transactionAmount = min(donorLeftover, beneficiaryLeftover, perPersonLeftover)
        Create transaction if amount >= MIN_TRANSACTION_DONATION_AMOUNT (500)
```

`TransactionService::hasUnmetNeeds(): bool` gates the on-demand donation button, so it deliberately mirrors path 2: scans `PeriodRepository::fetchProcessing()` × `BeneficiaryRepository::fetchByPeriod()` and returns true on the first beneficiary whose `getAmountForPeriod() − getSumAmountForBeneficiary()` exceeds the 500 floor (short-circuits). Same **processing** period set and same unmet-need math as `createForDonor`, so if the button shows, `createForDonor` has something to allocate. "Covered" counts allocated statuses incl. pending `NEW` (same as `allocateToBeneficiary`), so a need already pledged by unpaid instructions is treated as met. (So when no period is `processing`, `hasUnmetNeeds` is correctly false and the button hides.)

### Payment Method Preview (AJAX)
`TransactionController::getPaymentMethodPreview()` accepts `donorId`, `beneficiaryId`, `projectId`, `periodId` and returns:
- Payment type + label
- Account number or wire instructions
- donorLeftover, beneficiaryLeftover, perPersonLeftover, maxAmount

---

## Currency Handling

Always separate RSD and EUR queries:
```php
// RSD: bank transfer type
$qbRsd = $this->em->createQueryBuilder()
    ->select('COALESCE(SUM(pm.amount), 0)')
    ->from(DonorPaymentMethod::class, 'pm')
    ->where('pm.type = :bankType')
    ->setParameter('bankType', DonorPaymentMethod::TYPE_BANK_TRANSFER);
$rsdTotal = (int) $qbRsd->getQuery()->getSingleScalarResult();

// EUR: all other types
$qbEur = $this->em->createQueryBuilder()
    ->select('COALESCE(SUM(pm.amount), 0)')
    ->from(DonorPaymentMethod::class, 'pm')
    ->where('pm.type != :bankType')
    ->setParameter('bankType', DonorPaymentMethod::TYPE_BANK_TRANSFER);
$eurTotal = (int) $qbEur->getQuery()->getSingleScalarResult();

$totalRsd = $rsdTotal + Transaction::eurToRsd($eurTotal);
```

---

## Navigation Items

File: `themes/admin/partials/global/navigation.php`

```php
<!-- Shared: delegate + user -->
<?php if (in_array($loggedInEntityType, ['delegate', 'user'])): ?>
    <div class="item" data-href="/school/view/">
        <span class="tooltip"><?=$this->t('Label')?></span>
        <span class="itemAnchor">
            <svg><!-- icon --></svg>
            <?=$this->t('Label')?>
        </span>
    </div>
<?php endif; ?>

<!-- Admin/staff only -->
<?php if ($loggedInEntityType === 'user'): ?>
    <!-- items here -->
<?php endif; ?>

<!-- Super-admin only -->
<?php if($loggedInRole === \Skeletor\User\Entity\User::ROLE_ADMIN): ?>
    <!-- items here -->
<?php endif; ?>
```

---

## JavaScript Page Classes

For non-CRUD pages, create a simple class (no CrudPage extend needed):

```javascript
// public/assets/backend/js/pages/Statistics.js
export default class Statistics {
    constructor() {}
    init() {
        // Your initialization logic
    }
}
```

For tab switching:
```javascript
#initTabs() {
    const tabs = document.querySelectorAll('.container .tabs .tab');
    const contents = document.querySelectorAll('.container > .tabContent');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            contents.forEach(c => {
                c.classList.toggle('active', c.getAttribute('data-tab') === target);
            });
        });
    });
}
```

---

## XLSX Import/Export

Several controllers have XLSX import functionality using PhpSpreadsheet:

### Import Pattern
```php
public function import() {
    ini_set('max_execution_time', 3600);
    ini_set('memory_limit', '512M');
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $excel = $reader->load(APP_PATH . '/file.xlsx');
    $failedData = [];
    foreach ($excel->getSheet($excel->getFirstSheetIndex())->toArray() as $key => $data) {
        if ($key === 0) continue; // skip header
        // process row...
    }
}
```

### Export Pattern (failed rows)
```php
$spreadsheet = new Spreadsheet();
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$sheet = $writer->getSpreadsheet()->getActiveSheet();
$sheet->getCell('A1')->setValue('header');
foreach ($data as $row => $item) {
    $sheet->getCell('A' . ($row + 2))->setValue($item[0]);
}
$writer->save(APP_PATH . '/output.xlsx');
```

### Account Number Normalization
Bank account numbers are normalized to 18-digit format (Serbian standard):
```php
// 3-digit bank code + 13-digit account (zero-padded) + 2-digit checksum
```

## Payment QR (NBS IPS)

`Solidarity\Transaction\Service\QrCode` (`packages/Transaction/src/Service/QrCode.php`) turns a `Transaction` into an NBS IPS payment QR (the code Serbian banking apps scan). Depends on `chillerlan/php-qrcode` (`^5`, `composer require`).

- `forTransaction(Transaction): string` → an `<img src>`-ready **SVG data URI** (`data:image/svg+xml;base64,…`). SVG so no GD/Imagick needed.
- `buildIpsPayload(Transaction): string` → the raw IPS tag string; pure, so it's unit-tested (`tests/Unit/Transaction/QrCodeTest.php`) without the QR lib.
- `canBuildFor(Transaction): bool` → cheap guard so an Action can skip the QR without catching (true iff the transaction has a beneficiary).
- Autowires — inject into an Action, `setGlobalVariable('paymentQr', $this->qrCode->forTransaction($t))`, done.

IPS field mapping (tag order `K,V,C,R,N,I,SF,S,RO`, `|`-separated, UTF-8): `R` ← `accountNumber` (digits only); `N` ← `beneficiary->name` (a transaction with **no beneficiary throws `RuntimeException`** — there's no payee to print); `I` ← `amount` (always RSD; `amountEur` holds EUR — IPS is domestic-RSD only), formatted `RSD1234,00`; `SF` = `289` (citizen transfer); `S` ← `Donacija <referenceCode>`; `RO` = `00` + id. Revisit the `SF`/`RO` choices before production if a real *poziv na broj* is required.

## Transaction status changes (confirm / cancel)

Delegates **and** staff/admin confirm/cancel transactions — `transaction.edit` includes role 10. The rule: a transaction's status is changeable **while it is still open — `NEW` ("Čeka se uplata") or `WAITING_CONFIRMATION` ("Čeka se potvrda građana")** — and locked once finalized (`CONFIRMED`/`CANCELLED`/`PAID`/`EXPIRED`).

- **`donorConfirmed` is NOT a gate.** It only records whether the donor clicked the "I paid" button; donors frequently pay without clicking. Confirming attests to money **actually received**, so a `NEW`/not-`donorConfirmed` transaction can be confirmed — do **not** add a `donorConfirmed` guard.
- The confirm/cancel actions are gated **client-side** in `Transaction.js` `actionFilter` by the human-readable status label (`entity.columns.status`). It must allow **both** `NEW` and `WAITING_CONFIRMATION` — a prior bug allowed only `NEW`, so delegates couldn't act on `WAITING_CONFIRMATION` transactions.
- The **edit form** (`themes/admin/transaction/form.php`) makes Status read-only for delegates (`$statusEditable = false`) — they change status via the list buttons, not the modal.

Backend looseness (not yet enforced): the **single** `/transaction/updateStatus/*` endpoint calls `updateField` directly, bypassing `TransactionService::updateStatus()`'s `LOCKED_STATUSES` guard — so it *can* overwrite a finalized transaction; `updateStatusBulk` goes through the guarded service and can't. The "changeable only while open" rule is currently enforced only by the frontend `actionFilter`. To enforce server-side, route the single endpoint through `service->updateStatus()`.

## Data Visibility Rules

### Delegate Filtering
Delegates see only their own data throughout the app:
- **Beneficiaries:** filtered by `createdBy = delegateId` in `BeneficiaryService::fetchTableData()`
- **Schools:** filtered by `delegate = delegateId` in `SchoolController::tableHandler()`
- **Transactions:** filtered via `BeneficiaryController` delegate logic
- **Delegate list:** sees only own record

### Service-Level Filtering
Some services override `fetchTableData()` to apply role-based filters:
```php
public function fetchTableData($search, $filter, $offset, $limit, $order = [], $uncountableFilter = []) {
    if ($this->session->getStorage()->offsetGet('loggedInEntityType') === 'delegate') {
        $filter['createdBy'] = $this->session->getStorage()->offsetGet('loggedIn');
    }
    return parent::fetchTableData($search, $filter, $offset, $limit, $order, $uncountableFilter);
}
```

---

## Common Gotchas

1. **Educator = Beneficiary:** The terms "educator" and "oštećeni" in the UI and legacy code refer to the `Beneficiary` entity. Routes `/educator/*` exist alongside `/beneficiary/*`. Don't create a separate Educator package.
2. **Routes 404:** Trailing slash in route definition + `ignoreTrailingSlash=true` = 404. Remove trailing slash.
3. **Missing navigation:** Action classes don't auto-set `loggedIn*` template globals. Set them in constructor.
4. **"content" section reserved:** Don't use `$this->start('content')` in templates. Content is implicit.
5. **Permission denied on AJAX:** New AJAX endpoints need permission mapping or they get redirected to login (HTML response breaks JSON parsing — the catch block in JS shows a generic error instead of the server message).
6. **uncountableFilters with arrays:** The uncountableFilter section in `TableViewRepository` doesn't support arrays. Use scalar values only, or filter by a relationship field (e.g., `delegate` instead of `id` array).
7. **Form readonly params:** `Select` and `Text` constructors have `readOnly` as a late parameter. Use named params to avoid position issues.
8. **Validator blocking factory:** If the factory auto-resolves fields (like `accountNumber` from payment method matching), remove those checks from the validator.
9. **CLI commands:** Config maps CLI commands to controllers/actions. `CreateTransaction` is a CLI-triggered Action, not user-facing. Check `config.php` `cli` section for available commands.
10. **Custom DQL functions:** `DATE()` and `YEAR()` are registered as custom Doctrine string functions in `bootstrap.php`. Available in DQL queries.
11. **Programmatic transactions need `skipCsrf` + `skipDonorPaymentCheck`:** the `Transaction` validator (run by the filter on every `TransactionService::create()`) enforces a CSRF token *and* that the donor has a **persisted** `PaymentMethod` for the project. Neither applies to allocator-generated transactions — the CSRF was validated upstream, and matching was already done (on-demand by the donor's *chosen* form types, cron by pledged methods). `allocateToBeneficiary` sets both flags; without them you get an empty `ValidatorException` (its messages live on the *Transaction* validator, `$service->parseErrors()`, not the donation validator the frontend action reads). The allocator now catches that exception, logs the reason with context, and skips the one beneficiary rather than aborting the whole donor run. **Same trap in `TransactionFactory`:** it used to always re-derive the payment type via `matchPaymentType()` (donor's *persisted* methods for the project → "No matching payment type found" for on-demand). It now **uses the `paymentType`/`accountNumber`/`instructions` the allocator passes** when present, deriving only as a fallback for callers that don't supply one — so the filter must pass `paymentType` through (it does).
12b. **Registered periods are reconciled by id, not rebuilt.** `BeneficiaryFactory::syncRegisteredPeriods()` used to delete every `RegisteredPeriods` row and reinsert from the form, so anything the form could not express was destroyed — most sharply for a **delegate** editing a beneficiary registered under a project outside their assigned list: the project `<select>` has no matching `<option>`, posts back the placeholder's `-1`, `find(-1)` returns null, and the row (with its amount) vanished on an unrelated edit. It now matches submitted rows to stored ones by a hidden `registeredProjects[i][id]` input, falls back to the stored project/period for anything unresolvable, applies the amount either way, and removes **only** rows that were not submitted at all (a real Delete). Consequences to keep in step: the filter must pass `id` through and must not drop an id-carrying row with no period; the validator must skip the "Period je neophodan" check for those rows; and the hidden input has to survive in the template *and* be named in `RegisteredProjects.js`. A stale cached JS degrades safely (no ids → old delete-and-reinsert behaviour), which is why `Beneficiary.js` imports `RegisteredProjects.js?v=`. Covered by `BeneficiaryFactoryTest`, `BeneficiaryFilterTest`, `BeneficiaryValidatorTest`.

**`syncPaymentMethods()` in the same file is deliberately *not* reconciled the same way** — don't "fix" the asymmetry. Its form renders all four payment types as checkboxes unconditionally, so a submission always describes the complete desired state and an unchecked box is a real removal; there is no unexpressible case to preserve. It did carry a related trap, since removed: rows were gated on resolving a project (defaulting to the first registered period's), which was then assigned to nothing because `Beneficiary\Entity\PaymentMethod::$project` is commented out. A beneficiary reaching it with no registered periods therefore had every payment method deleted and none written back — the account number gone silently. Only the validator's unrelated "at least one period" rule kept it out of reach.

12. **Inactive-period form dropdowns drop data:** the beneficiary edit form builds the period `<option>` list from `active` periods only (`getEntities(['active' => true])`). A `RegisteredPeriods` on an *inactive* period (its FK is NOT NULL, so it's never truly missing) then has no option → the dropdown shows the empty "Izaberite Period" placeholder, and **saving deletes the registration** (`syncRegisteredPeriods` deletes-then-reinserts, and the placeholder submits no valid period). `BeneficiaryController::form()` fixes this by merging the model's own registered periods into the option list even when inactive (marked "(neaktivan)"). Reconcile migration completeness with `php public/cli.php migrateLegacy verify` (total registered-amount invariant + lists inactive-period registrations). **Same active-only dropdown in the transaction edit form** (`TransactionController::form()`): a transaction's period is a `processing` period, which needn't be `active`, so it showed "---". Fixed the same way (merge the edited transaction's own period). No data loss there, though — `TransactionFactory::compileEntityForUpdate` only touches `status`/`comment`, never the period — but the empty "---" tripped the Select's `required` rule.
