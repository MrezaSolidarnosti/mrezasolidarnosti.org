<?php

namespace Solidarity\Beneficiary\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Validator\ValidatorInterface;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\User\Entity\User;

class Beneficiary implements ValidatorInterface
{
    private array $messages = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        // Required, NOT optional-with-a-default. PHP-DI's ReflectionBasedAutowiring skips
        // optional parameters outright (`if ($parameter->isOptional()) { continue; }`), so a
        // defaulted one is never autowired: it would be null in the app forever, the exemption
        // below would silently never apply, and tests that pass a session explicitly would
        // still go green.
        private Session $session,
    ) {
    }

    public function isValid(array $data): bool
    {
        $this->messages = [];
        if (empty($data['name'])) {
            $this->messages['name'][] = 'Ime je neophodno.';
        }
        // A delegate is what makes a beneficiary visible to them (Beneficiary::fetchTableData
        // filters on createdBy), so one is still required — but it may now arrive from either
        // side: the chosen school's delegate for MSP, or a directly picked delegate for MSPR,
        // which has no schools. The old message named only the school, which was the one
        // thing an MSPR beneficiary could never supply.
        if (!$data['createdBy']) {
            $this->messages['createdBy'][] = 'Delegat je obavezan: izaberite školu koja ima delegata ili delegata direktno.';
        }

        if (empty($data['paymentMethods'])) {
            $this->messages['paymentMethods'][] = 'Bar jedan metod plaćanja mora biti unet.';
        } else {
            foreach ($data['paymentMethods'] as $index => $row) {
                if ($row['type'] === 1) {
                    // Budget of the Republic of Serbia
                    if (str_starts_with($row['accountNumber'], '840')) {
                        $this->messages['paymentMethods'][] = 'Broj računa pripada budzetu Republike Srbije.';
                    }
                    // Eurobank Direktna
                    if (str_starts_with($row['accountNumber'], '150')) {
                        $this->messages['paymentMethods'][] = 'Broj računa pripada banci "Eurobank Direktna" koja više ne postoji.';
                    }
                    // MTS Bank
                    if (str_starts_with($row['accountNumber'], '360')) {
                        $this->messages['paymentMethods'][] = 'Broj računa pripada banci "MTS Bank" koja više ne postoji.';
                    }
                    if (!$this->validateAccountNumber($row['accountNumber'])) {
                        $this->messages['paymentMethods'][] = 'Broj računa nije validan, kontrolni broj je pogrešan.';
                    }
                    // Check account number uniqueness across beneficiaries
                    if (!empty($row['accountNumber'])) {
                        $this->validateAccountNumberUniqueness($row['accountNumber'], $data['id'] ?? null);
                    }
                } else if ($row['type'] === 2) {
                    // todo check if wire info empty?
                }

            }
        }

        if (empty($data['registeredPeriods'])) {
            $this->messages['registeredPeriods'][] = 'Bar jedan period mora biti unet.';
        } else {
            // todo might need to fetch period data, to determine limit, for half periods, limit should be halved
            foreach ($data['registeredPeriods'] as $index => $row) {
                // A stored row (one carrying an id) posts back without a usable period when the
                // form could not render its option. It is preserved as-is rather than rewritten,
                // so demanding a period here would refuse a save over a row the user never
                // touched — and could not have fixed, since the missing option is the very one
                // they would need. A new row still has to name its period.
                $isStoredRow = isset($row['id']) && $row['id'] !== '' && (int) $row['id'] > 0;
                if (empty($row['period']) && !$isStoredRow) {
                    $this->messages['registeredPeriods'][] = sprintf('Period je neophodan za red %d.', $index + 1);
                }
                if (!isset($row['amount']) || $row['amount'] <= 0) {
                    $this->messages['registeredPeriods'][] = sprintf('Iznos mora biti veći od nule za red %d.', $index + 1);
                } elseif (!$this->isAdmin()) {
                    // Admins are exempt. The period maximum is a guard rail for delegates and
                    // staff; an admin typing a figure above it is making a deliberate
                    // exception, and there is nowhere else in the UI to record one. The
                    // amount-must-be-positive rule above still applies to everyone — that one
                    // is nonsense rather than policy.
                    $limit = $this->limitForPeriod($row['period']);
                    if ($row['amount'] > $limit) {
                        $this->messages['registeredPeriods'][] = sprintf(
                            'Iznos u redu %d je veći od limita od %s.',
                            $index + 1,
                            number_format($limit, 0)
                        );
                    }
                }
            }
        }

        return empty($this->messages);
    }

    /**
     * Deliberately NOT Session::isAdminLoggedIn(). That method compares against
     * Skeletor\User\Model\User::ROLE_ADMIN, and Model\User declares only STATUS_* — the
     * constant does not exist there, so calling it is a fatal. Nothing in this app reaches it
     * today (it sits behind CrudService::APPLY_TENANT_FILTER), which is why it has gone
     * unnoticed. The role integer is compared directly instead, as permissions.php does.
     *
     * The entity type is checked too: role is only meaningful within a type, and delegates
     * carry their own numbering.
     */
    private function isAdmin(): bool
    {
        return $this->session->getLoggedInEntityType() === 'user'
            && (int) $this->session->getLoggedInRole() === User::ROLE_ADMIN;
    }

    /**
     * How much one beneficiary may be registered for in a given period.
     *
     * A period can carry its own `maxAmount` — a round where less money is available, or
     * more. It overrides the global `Beneficiary::MONTHLY_LIMIT` only when it is set to
     * something above zero; blank and 0 both mean "use the global limit", which is what the
     * hint under the field on the period form promises.
     */
    private function limitForPeriod(mixed $periodId): int
    {
        $global = \Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT;
        if (!$periodId) {
            return $global;
        }

        $period = $this->entityManager->getRepository(\Solidarity\Period\Entity\Period::class)->find($periodId);

        return ($period?->maxAmount ?? 0) > 0 ? $period->maxAmount : $global;
    }

    private function validateAccountNumberUniqueness(string $accountNumber, ?int $beneficiaryId): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        // Join the beneficiary so the message can name the conflicting record — the bare
        // "already assigned to another user" is undebuggable when the real cause is a
        // duplicate account imported onto a second beneficiary. Self is still excluded in
        // SQL (so editing a beneficiary never collides with its own account).
        $qb->select('b.id', 'b.name')
            ->from(PaymentMethod::class, 'pm')
            ->join('pm.beneficiary', 'b')
            ->where('pm.accountNumber = :accountNumber')
            ->setParameter('accountNumber', $accountNumber);

        if ($beneficiaryId) {
            $qb->andWhere('b.id != :beneficiaryId')
                ->setParameter('beneficiaryId', $beneficiaryId);
        }

        $qb->setMaxResults(1);
        $conflict = $qb->getQuery()->getOneOrNullResult();

        if ($conflict) {
            $this->messages['paymentMethods'][] = sprintf(
                'Broj računa %s je već dodeljen korisniku „%s" (#%d).',
                $accountNumber,
                $conflict['name'],
                $conflict['id']
            );
        }
    }

    private function validateAccountNumber(string $accountNumber): bool
    {
        $controlNumber = $this->mod97(substr($accountNumber, 0, -2));

        return str_pad($controlNumber, 2, '0', STR_PAD_LEFT) === substr($accountNumber, -2);
    }

    private function mod97(string $accountNumber, int $base = 100): int
    {
        $controlNumber = 0;

        for ($x = strlen($accountNumber) - 1; $x >= 0; --$x) {
            $num = (int) $accountNumber[$x];
            $controlNumber = ($controlNumber + ($base * $num)) % 97;
            $base = ($base * 10) % 97;
        }

        return 98 - $controlNumber;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
