<?php

namespace Solidarity\Beneficiary\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Validator\ValidatorInterface;
use Solidarity\Beneficiary\Entity\PaymentMethod;

class Beneficiary implements ValidatorInterface
{
    private array $messages = [];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function isValid(array $data): bool
    {
        $this->messages = [];
        if (empty($data['name'])) {
            $this->messages['name'][] = 'Ime je neophodno.';
        }
        if (!$data['createdBy']) {
            $this->messages['createdBy'][] = 'School has no delegate assigned.';
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
                // A period may carry its own maximum, which overrides the global limit in both
                // directions — a leaner round caps lower, a catch-up round allows more. Blank and
                // 0 both mean "use the global limit": MigrateLegacy writes 0 for every legacy
                // period, so treating 0 as a real cap would reject everything.
                $limit = \Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT;
                if (!empty($row['period'])) {
                    $period = $this->entityManager
                        ->getRepository(\Solidarity\Period\Entity\Period::class)
                        ->find($row['period']);
                    if ($period && (int) $period->maxAmount > 0) {
                        $limit = (int) $period->maxAmount;
                    }
                }

                if (!isset($row['amount']) || $row['amount'] <= 0) {
                    $this->messages['registeredPeriods'][] = sprintf('Iznos mora biti veći od nule za red %d.', $index + 1);
                } elseif ($row['amount'] > $limit) {
                    $this->messages['registeredPeriods'][] = sprintf(
                        'Iznos u redu %d je veći od limita od %s.',
                        $index + 1,
                        number_format($limit, 0)
                    );
                }
            }
        }

        return empty($this->messages);
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
