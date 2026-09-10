# The donor-facing donation flow

How a donor goes from the donate block to a payment instruction, and what happens to that
instruction afterwards. This is the money path — most of the sharp edges in the app live here.

## The donate block (`themes/frontend/blocks/donate.php`)

Three project cards; picking one opens a modal form. There is **no longer an external entry
point that switches the action** — an older flow landed here via `?action=instruction`, and
every trace of that is gone. The donor lands on the page, picks a project, and picks a mode.

**Monthly vs one-time is a mode toggle, not two forms.** Both modes post to the same action
with a different `data-mode`; the toggle carries `data-action`/`data-label` and there is a
single submit button. The container kept the id `frequencyFields` deliberately — existing
per-project CSS keys off it.

- **Monthly** — saves/updates the donor's `PaymentMethod`s. The cron allocates from them.
- **One-time** — creates an instruction immediately via `createForDonor()`. Nothing is saved
  to `PaymentMethod`. See the known gap in `operations.md`.

The two modes keep **separate amount state** (`#amountsByMode` in `Form.js`), so switching
back and forth doesn't leak a monthly pledge into a one-time donation or vice versa.

### JS contract (`public/assets/frontend/js/donate/`)

- **`Form.js` reads `this.#form.action`, not `e.submitter.formAction`.** With no `formaction`
  attribute on the button, `submitter.formAction` returns the *document URL* — the post
  silently goes to the page instead of the endpoint and nothing is ever saved. This was the
  original "nothing happens, no data in db" bug. Don't "simplify" it back.
- The response is JSON-parsed inside a try/catch that logs the action and status, because a
  PHP fatal returns HTML and the bare `res.json()` failure was invisible.
- `Form.js` emits **`donationSaved`**; `Donate.js` listens for it and marks the card selected.
  **Opening the modal is not choosing** — `showForm` must not set the selection, or closing
  the modal with X leaves the wrong card highlighted.
- Card pre-select reuses the hover visual (`#applyVisual()`); `#preselectExistingProject()`
  and `#prefillIfSaved()` restore the donor's stored choice and amounts.
- Bump the `?v=` on the asset when you touch these; the box serves them cached.

### Route

`/donor/createInstruction` (**not** `createTransaction` — that 404s).

## Project ids are hardcoded

The block and the services assume **MSP = 1, MSPR = 2**. A dev DB seeded in a different order
(MSP=2, MSPR=3) silently saves against the wrong project — the form works, the data is wrong.
`MigrateLegacy::ensureProject()` seeds both from a `PROJECTS` const. If ids drift, renumber
with FK-aware SQL and apply strictly in order (**all `2→1` before any `3→2`**).

## `hasUnmetNeeds()` and `NoNeedsException`

```php
Transaction::hasUnmetNeeds(?Donor $donor = null, array $projects = [], array $paymentTypes = [])
```

The donor is **optional** — the per-person cap only applies when one is given, so the cron and
the "are there needs at all?" question use `null`. Narrowing by project and payment type is
what makes the answer meaningful: there may be needs overall but none the donor's chosen
project or payment method can cover.

`Donor::createInstruction` walks a ladder of increasingly specific checks and throws
`NoNeedsException` (`packages/Donor/src/Service/NoNeedsException.php`) with the message that
matches the rung reached — "no needs at all", "none for this project", "none this payment
method can cover", "amount too small". Rungs 2 and 3 deliberately pass a `null` donor so the
message describes the *system*, not the donor's remaining cap.

## Allocation

Three entry points sharing `allocateToBeneficiary()`:

| Method | Used by |
|---|---|
| `createBalancedForDonor($donor, $projects)` | the cron |
| `createForDonor($donor, $projects, $budgets)` | the one-time frontend path |
| `allocateAmount()` | tests only |

Constraints: `MIN_TRANSACTION_DONATION_AMOUNT = 500`, `PER_PERSON_LIMIT = 30000`,
`EUR_TO_RSD_RATE = 117.5`. The **minimum slice escalates to 10,000** once the total budget is
over 100,000, so a large donor doesn't get shredded into hundreds of tiny instructions.

**Nothing is ever created below 500.** `allocateToBeneficiary` takes the `min()` of the three
remaining budgets and skips the beneficiary if the result is under the floor — the floor is
applied *after* the min, so a large budget still can't produce a 40 RSD instruction against a
nearly-satisfied beneficiary.

`allocateToBeneficiary(..., bool $manual = false)` sets `Transaction::manual`
(`public bool $manual = false`) — **true for one-time, false for cron**. It exists purely for
reporting: which donations were made by hand vs generated.

## Instruction lifecycle & the expire cron

`Solidarity\Backend\Action\ExpireInstructions` — `php public/cli.php expireInstructions run`
(`dry` to preview), batch size 100.

- Window is **72 hours** (`EXPIRE_AFTER = '-72 hours'`), matching what the donor is promised.
- **It must run immediately before `createTransactions`** so freed budget is reallocated in
  the same cycle.
- **The window must be strictly shorter than the gap between cron runs.** With a 72h window
  and an exactly-72h gap, an instruction is a few minutes short when the cron looks at it and
  survives an entire extra cycle. This is why Thursday's cron runs an hour later than Monday's
  — a scheduling fact that lives outside the code, so don't "tidy" the crontab times.
- `donorSawIt()` compares `donor->lastVisit > transaction->getCreatedAt()` and picks a
  different comment: expired-unseen vs expired-after-the-donor-saw-it.

### `lastVisit` (ported from the legacy app)

`Donor::$lastVisit` (`public ?\DateTime`), stamped by `Session::touchVisit()` from
`BaseAction`, i.e. on **every frontend request**. So:

- `DonorRepository::touchLastVisit(int $donorId, int $throttleSeconds = 300)` throttles the
  write. **The `andWhere` needs its parentheses**:
  `'(d.lastVisit IS NULL OR d.lastVisit < :threshold)'` — without them the OR escapes the
  donor-id condition and the UPDATE hits the whole table.
- `touchVisit()` is guarded and swallows exceptions. A visit stamp is never worth 500-ing a
  page over.
- **`lastVisit`, not `lastLogin`** — sessions last 30 days, so a donor who reads the
  instructions email and clicks through has visited without logging in again.

## Delegate payout XLSX — a round-trip format

`Transaction::compileXlsxTransactionList($transactions, $school)` writes the list a delegate
pays from; `TransactionController::uploadTransactionList()` reads the returned file back and
**writes transaction statuses from its cells**. Two pieces of code that never reference each
other, agreeing on a layout:

- Header in **row 1**, **row 2 is blank**, data starts at **row 3**. The reader skips array
  keys `< 2`. The blank row and the skip move together or the first payout is dropped.
- Column A = id, C = amount (compared against the stored transaction; a mismatch rejects the
  row), **E = status**.
- Column D (account number) is written with a **trailing space** so Excel treats an 18-digit
  account as text instead of reformatting it into scientific notation. It looks like a typo.
  It is not. The reader never reads column D.
- The status dropdown is `"Plaćeno,Neplaćeno"` with `allowBlank(false)`. This matters more
  than it looks: the reader's `switch` has **`default: STATUS_CANCELLED`**, so a blank or
  misspelled cell silently *cancels* a payout rather than failing.
- The filename is `data/lists/<name>.xlsx` via `listFileName()`, which strips everything that
  isn't `\p{L}`/`\p{N}` (so a school name can't steer the write out of the directory) and
  falls back to `lista` when nothing usable remains. **Keep `\p{L}`** — the existing files are
  named `BibliotekaVladaAksentijević.xlsx`; an ASCII filter would rename every school with a
  diacritic.
- **The generator currently has no caller** — `sendTransactionListToAffectedDelegates()` is
  gone from `TransactionController`. The upload half is still wired to a route, but that
  route is **not in `permissions.php`**, and unmapped paths are denied by default — so the
  whole round trip is switched off. This is deliberate for the first production release;
  re-enabling it means adding `/transaction/uploadTransactionList*` to the `routes` map.
- `toArray()` formats by default, so the reader gets **strings**: `getById('11')` and an
  amount compared with a loose `!=`. `CrudRepository::updateField()` interpolates that id
  straight into DQL rather than binding it — worth fixing whenever the upload is revived.
- The reader calls `service->updateField()`, not `updateStatus()`, so it **bypasses
  `LOCKED_STATUSES`**: re-uploading an old list rewrites payouts already marked PAID.
- The uploaded filename is sanitised (`safeUploadName()`: `basename()` + an
  `[A-Za-z0-9._-]` filter + no leading dots, falling back to `upload.xlsx`) before it
  reaches `moveTo()`. The extension check is case-insensitive — Excel on Windows returns
  `.XLSX`, which a case-sensitive check would hand to the `Xls` reader.

Covered by `tests/Unit/Transaction/XlsxTransactionListTest.php` (the writer) and
`tests/Integration/Backend/UploadTransactionListTest.php` (the reader).

## Related fixes worth not regressing

- **`ConfirmPayment` CSRF**: a failed check must `return`, not fall through. It previously
  logged the failure and carried on.
- **`AjaxCrudController`'s logger call is commented out**, so a missing DB column (e.g. when
  `transaction.manual` or `donor.lastVisit` were added) surfaces in the dashboard as a bare
  "An unexpected error occurred. Please try again." with nothing in the logs. If a dashboard
  save fails inexplicably, **check the schema first**.
- After editing `bootstrap.php`, stale OPcache produces an `ArgumentCountError` that survives
  the fix. Restart FPM (see `operations.md`).
