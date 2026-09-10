# Entity Reference

## Table of Contents
1. [Transaction](#transaction)
2. [Donor](#donor)
3. [Donor PaymentMethod](#donor-paymentmethod)
4. [Beneficiary](#beneficiary)
5. [Beneficiary RegisteredPeriods](#beneficiary-registeredperiods)
6. [Beneficiary PaymentMethod](#beneficiary-paymentmethod)
7. [Delegate](#delegate)
8. [School](#school)
9. [Period](#period)
10. [Project](#project)

---

## Transaction
**Entity:** `Solidarity\Transaction\Entity\Transaction`
**Table:** `transaction`
**Path:** `packages/Transaction/src/Entity/Transaction.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | Auto-increment (Timestampable trait) |
| amount | int | Amount in RSD |
| amountEur | int | Amount in EUR (for non-bank transfers) |
| status | smallint | See status constants |
| paymentType | smallint | Matches PaymentMethod types |
| accountNumber | string(32), nullable | Beneficiary's bank account |
| instructions | string(512), nullable | Wire transfer instructions |
| donorConfirmed | bool | Whether donor confirmed payment |
| comment | string(1024), nullable | |
| paymentCode | string(256), nullable | Payment institution code |
| createdAt | datetime | Auto-set (Timestampable) |
| updatedAt | datetime | Auto-set (Timestampable) |

### Relationships
- `ManyToOne` → **Project** (projectId)
- `ManyToOne` → **Period** (periodId)
- `ManyToOne` → **Donor** (donorId)
- `ManyToOne` → **Beneficiary** (beneficiaryId)

### Status Constants
| Constant | Value | Label |
|----------|-------|-------|
| STATUS_NEW | 1 | Nova |
| STATUS_WAITING_CONFIRMATION | 2 | Ceka se uplata |
| STATUS_CONFIRMED | 3 | Potvrdjeno |
| STATUS_CANCELLED | 4 | Otkazano |
| STATUS_NOT_PAID | 5 | Nije placeno |
| STATUS_EXPIRED | 6 | Isteklo |
| STATUS_PAID | 7 | Placeno |

**Allocated statuses** (count as "money committed"): NEW, WAITING_CONFIRMATION, CONFIRMED, PAID

### Constants
- `PER_PERSON_LIMIT = 30000` — max RSD between any donor-beneficiary pair
- `EUR_TO_RSD_RATE = 117.5`

### Static Methods
- `eurToRsd(int $eurAmount): int`
- `rsdToEur(int $rsdAmount): int`
- `getDisplayAmount(Transaction $t): int` — returns EUR or RSD based on type
- `getDisplayCurrency(Transaction $t): string`
- `getHrStatus(int $status): string`
- `getHrStatuses(): array`

---

## Donor
**Entity:** `Solidarity\Donor\Entity\Donor`
**Table:** `donor`
**Path:** `packages/Donor/src/Entity/Donor.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| email | string | |
| firstName | string | |
| lastName | string | |
| status | int | |
| isActive | bool | |
| wantsToDonateTo | int | DONATE_TO_SCHOOL or DONATE_TO_UNI |

### Relationships
- `OneToMany` → **PaymentMethod** (paymentMethods)
- `OneToMany` → **Transaction** (transactions)
- `ManyToMany` → **Project** (projects)

### Status Constants
| Constant | Value | Label |
|----------|-------|-------|
| STATUS_NEW | 1 | New |
| STATUS_VERIFIED | 2 | Potvrdjen email |
| STATUS_PROBLEM | 3 | Problem |
| STATUS_DELETED | 4 | Obrisan |

### Key Methods
- `getPaymentMethodsForProject(Project $project): array` — returns PaymentMethods for specific project

---

## Donor PaymentMethod
**Entity:** `Solidarity\Donor\Entity\PaymentMethod`
**Table:** `donorPaymentMethod`
**Path:** `packages/Donor/src/Entity/PaymentMethod.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| type | smallint | 1=bank, 2=wire, 3=WU, 4=MG |
| amount | int | Pledged amount (in currency field's denomination) |
| monthly | smallint | 1=monthly recurring, 0=one-time |
| currency | smallint | 1=RSD, 2=EUR |

### Relationships
- `ManyToOne` → **Donor**
- `ManyToOne` → **Project**

### Type Constants
| Constant | Value | Label |
|----------|-------|-------|
| TYPE_BANK_TRANSFER | 1 | Bankovni transfer (lokalni) |
| TYPE_WIRE_TRANSFER | 2 | Bankovni transfer (medjunarodni) |
| TYPE_WESTERN_UNION | 3 | Western Union |
| TYPE_MONEYGRAM | 4 | Moneygram |

### Currency Constants
| Constant | Value |
|----------|-------|
| CURRENCY_RSD | 1 |
| CURRENCY_EUR | 2 |

**Rule:** TYPE_BANK_TRANSFER amounts are always in RSD. All other types are in EUR.

---

## Beneficiary
**Entity:** `Solidarity\Beneficiary\Entity\Beneficiary`
**Table:** `beneficiary`
**Path:** `packages/Beneficiary/src/Entity/Beneficiary.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| name | string | |
| status | int | |
| comment | string, nullable | |

### Relationships
- `ManyToOne` → **School** (school)
- `ManyToOne` → **Delegate** (createdBy)
- `OneToMany` → **RegisteredPeriods** (registeredPeriods)
- `OneToMany` → **Transaction** (transactions)
- `OneToMany` → **PaymentMethod** (paymentMethods)

### Status Constants
| Constant | Value |
|----------|-------|
| STATUS_NEW | 1 |
| STATUS_DELETED | 2 |
| STATUS_GAVE_UP | 4 |
| STATUS_PROBLEM | 7 |

### Constants
- `MONTHLY_LIMIT = 240000` RSD

### Key Methods
- `getAmountForPeriod(Period $period): int` — gets allocated amount from RegisteredPeriods

---

## Beneficiary RegisteredPeriods
**Entity:** `Solidarity\Beneficiary\Entity\RegisteredPeriods`
**Table:** `beneficiaryRegisteredPeriods`
**Path:** `packages/Beneficiary/src/Entity/RegisteredPeriods.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| amount | int | Allocated/requested amount for this period |

### Relationships
- `ManyToOne` → **Period**
- `ManyToOne` → **Project**
- `ManyToOne` → **Beneficiary**

---

## Beneficiary PaymentMethod
**Entity:** `Solidarity\Beneficiary\Entity\PaymentMethod`
**Table:** `beneficiaryPaymentMethod`
**Path:** `packages/Beneficiary/src/Entity/PaymentMethod.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| type | smallint | Same type constants as Donor PM |
| accountNumber | string, nullable | For bank transfers |
| wireInstructions | string, nullable | For wire/WU/MG |

### Relationships
- `ManyToOne` → **Beneficiary**

Uses same TYPE_ constants as Donor PaymentMethod.

---

## Delegate
**Entity:** `Solidarity\Delegate\Entity\Delegate`
**Table:** `delegate`
**Path:** `packages/Delegate/src/Entity/Delegate.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| email | string | |
| name | string | |
| phone | string | |
| status | int | |
| verifiedBy | string, nullable | |

### Relationships
- `OneToMany` → **School** (schools)
- `ManyToMany` → **Project** (projects)

### Status Constants
| Constant | Value |
|----------|-------|
| STATUS_NEW | 1 |
| STATUS_VERIFIED | 2 |
| STATUS_PROBLEM | 3 |

### Auth
- Role: **10** (fixed via `getAuthRole()`)
- Supports magic link login via `DelegateLoginController`
- Password-less — uses email-based magic links only

### Auto-Assignment Behavior
When a delegate is saved with schools:
- Orphaned beneficiaries (no `createdBy`) in those schools are auto-assigned to this delegate
- When schools are removed from delegate, `createdBy` is nullified for beneficiaries in those schools
- Delegates can only see their own record in the delegate list (service overrides `fetchTableData`)

---

## School
**Entity:** `Solidarity\School\Entity\School`
**Table:** `school`
**Path:** `packages/School/src/Entity/School.php`

### Relationships
- `OneToMany` → **Beneficiary** (beneficiaries)
- `ManyToOne` → **SchoolType** (type)
- `ManyToOne` → **City** (city)
- `ManyToOne` → **Delegate** (delegate)

---

## Period
**Entity:** `Solidarity\Period\Entity\Period`
**Table:** `period`
**Path:** `packages/Period/src/Entity/Period.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| month | int | |
| year | int | |
| type | string | 'first-half', 'second-half', 'full' |
| maxAmount | int | 0 = no per-period override, never null — see below |
| active | bool | |
| processing | bool | Currently being processed for transaction creation |

### Relationships
- `ManyToOne` → **Project**

### Key Methods
- `getLabel(): string` — returns `"{project.code}-{month}-{year}-{type}"`

### `maxAmount` is 0, never null
The column is `NOT NULL` and 0 is the established spelling for "no per-period override": `MigrateLegacy` writes it for every legacy period, and `Beneficiary\Validator` reads anything `<= 0` as "fall back to the global limit". The property is a plain `int` for that reason — declaring it `?int` previously invited `Period\Filter` to write null for a blank "Max iznos" input, which was a `NotNullConstraintViolationException` (a 500 on save) until `PeriodFactoryTest` caught it.

---

## Project
**Entity:** `Solidarity\Transaction\Entity\Project`
**Table:** `project`
**Path:** `packages/Transaction/src/Entity/Project.php`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| name | string | |
| code | string | e.g., 'MSP', 'MSPR' |
| logo | string, nullable | |

### Relationships
- `OneToMany` → **Period**
- `ManyToMany` → **Delegate**
