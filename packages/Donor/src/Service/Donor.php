<?php
namespace Solidarity\Donor\Service;

use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Login\Service\MagicLinkService;
use Solidarity\Donor\Filter\DonorProfileData;
use Solidarity\Donor\Repository\DonorRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Donor\Validator\DonorDonationData;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Tamtamchik\SimpleFlash\Flash;

class Donor extends TableView
{

    /**
     * @param DonorRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        DonorRepository $repo, Session $user, Logger $logger, \Solidarity\Donor\Filter\Donor $filter,
        private Mailer $mailer, private Project $project, private MagicLinkService $magicLinkService,
        private DonorProfileData $donorProfileDataFilter,
        private \Solidarity\Donor\Validator\DonorProfileData $donorProfileDataValidator,
        private DonorDonationData $donorDonationDataValidator,
        private \Solidarity\Donor\Filter\DonorDonationData $donorDonationDataFilter,
        private TransactionService $transaction, private QrCode $qrCode
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getDonorsByProject($project)
    {
        return $this->repo->getDonorsByProject($project);
    }

    public function create(array $data)
    {
        $entity = $this->getEntities(['email' => $data['email']]);
        if (count($entity)) {
            throw new \Exception('Donor already exists');
        } else {
            $entity = parent::create($data);

            $token = $this->magicLinkService->requestMagicLink($entity->email, 'donor', false);
            $this->mailer->sendDonorRegisteredMail($entity->email, $entity->firstName .' '. $entity->lastName, $token);
        }

        return $entity;
    }

    /**
     * Send a magic-link login email to an existing donor. Silent if the email
     * isn't registered, so the form can't be used to enumerate accounts.
     */
    public function requestLoginLink(string $email): void
    {
        $donor = $this->repo->findByEmail($email);
        if (!$donor) {
            return;
        }

        $token = $this->magicLinkService->requestMagicLink($email, 'donor', false);
        $this->mailer->sendDonorLoginMail($email, $donor->getDisplayName(), $token);
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Donor\Entity\Donor $donor */
        foreach ($entities as $donor) {
            $projects = [];
            foreach ($donor->projects as $project) {
                $projects[] = $project->code;
            }

            // Pledged amounts per project (from payment methods, converted to RSD)
            $pledgedByProject = [];
            foreach ($donor->paymentMethods as $pm) {
                $code = $pm->project->code;
                $amount = $pm->type !== PaymentMethod::TYPE_BANK_TRANSFER
                    ? Transaction::eurToRsd($pm->amount)
                    : $pm->amount;
                $pledgedByProject[$code] = ($pledgedByProject[$code] ?? 0) + $amount;
            }
            $pledgedParts = [];
            foreach ($pledgedByProject as $code => $amount) {
                $pledgedParts[] = $code . ' (' . number_format($amount, 0, '.', ',') . ')';
            }

            // Confirmed/paid amounts per project (from transactions)
            $paidByProject = [];
            foreach ($donor->transactions as $transaction) {
                if ($transaction->status === Transaction::STATUS_CONFIRMED || $transaction->status === Transaction::STATUS_PAID) {
                    $code = $transaction->project->code;
                    $paidByProject[$code] = ($paidByProject[$code] ?? 0) + $transaction->amount;
                }
            }
            $paidParts = [];
            foreach ($paidByProject as $code => $amount) {
                $paidParts[] = $code . ' (' . number_format($amount, 0, '.', ',') . ')';
            }

            // Payment methods display
            $methods = '';
            foreach ($donor->paymentMethods as $pm) {
                $methods .= PaymentMethod::getHrType($pm->type) . ' - '
                    . number_format($pm->amount, 0, '.', ',') . ' '
                    . PaymentMethod::getCurrency($pm->currency)
                    . '<br>';
            }

            $itemData = [
                'id' => $donor->getId(),
                'email' =>  [
                    'value' => $donor->email .' ('. implode(', ', $projects) . ')',
                    'editColumn' => true,
                ],
                'p.id' => implode(', ', $projects),
                'pledgedAmount' => implode(' | ', $pledgedParts),
                'paidAmount' => implode(' | ', $paidParts),
                'paymentMethods' => $methods,
                'status' => \Solidarity\Donor\Entity\Donor::getHrStatus($donor->status),
                'isActive' => ($donor->isActive) ? 'Da': 'Ne',
                'createdAt' => $donor->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $donor->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'p.id', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'pledgedAmount', 'label' => 'Obećano'],
            ['name' => 'paidAmount', 'label' => 'Uplaćeno'],
            ['name' => 'paymentMethods', 'label' => 'Način uplate'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Donor\Entity\Donor::getHrStatuses()],
            ['name' => 'isActive', 'label' => 'Aktivan', 'filterData' => [0 => 'No', 1 => 'Yes']],
            ['name' => 'createdAt', 'label' => 'Registrovan'],
        ];

        return $columnDefinitions;
    }

    public function getDonorCount(int $status, ?bool $isActive): int
    {
        return $this->repo->getDonorCount($status, $isActive);
    }

    public function getFilterErrors()
    {
        return $this->filter->getErrors();
    }

    public function getProfileDataFilterErrors(): array
    {
        return $this->donorProfileDataValidator->getMessages();
    }

    public function getDonationDataFilterErrors(): array
    {
        return $this->donorDonationDataValidator->getMessages();
    }

    public function updateProfileData(array $data): void
    {
        if (!$this->donorProfileDataValidator->isValid($data)) {
            throw new ValidatorException();
        }
        $filteredData = $this->donorProfileDataFilter->filter($data);
        $this->repo->updateProfileData(
            $filteredData['id'],
            $filteredData['firstName'],
            $filteredData['lastName']
        );
    }

    public function updateDonationData(array $data): void
    {
        $filteredData = $this->donorDonationDataFilter->filter($data);
        if (!$this->donorDonationDataValidator->isValid($filteredData)) {
            throw new ValidatorException();
        }
        $this->repo->updateDonationData($filteredData);
    }

    public function getInstructions(int $donorId, int $page = 1, int $perPage = 10): array
    {
        $donor = $this->repo->getById($donorId);
        if (!$donor) {
            throw new \Exception('Donor not found');
        }

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $transactions = $this->transaction->getInstructionsForDonor($donor, $offset, $perPage);
        $total = $this->transaction->getInstructionsCountForDonor($donor);
        $items = [];
        foreach ($transactions as $transaction) {
            $qr = '';
            if ($this->qrCode->canBuildFor($transaction)) {
                $qr = $this->qrCode->forTransaction($transaction);
            }
            $items[] = [
                'id' => $transaction->id,
                'beneficiaryName' => $transaction->beneficiary?->name ?? 'N/A',
                'amount' => number_format($transaction->amount, 0),
                'referenceCode' => $transaction->getReferenceCode(),
                'createdAt' => $transaction->getCreatedAt()->format('d.m.Y'),
                'expiresAt' => $transaction->status === Transaction::STATUS_NEW ?
                    $transaction->getExpiryDate()->format('d.m.Y h:i') :
                    null,
                'status' => ['label' => Transaction::getHrStatus($transaction->status), 'value' => $transaction->status],
                'projectId' => $transaction->project->id,
                'paymentType' => $transaction->paymentType,
                'accountNumber' => $transaction->accountNumber,
                'qrCode' => $qr
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * On-demand donation: match beneficiaries to the donor's chosen projects and payment
     * types and create instructions now. Returns the total RSD allocated (0 when nothing
     * currently matches the donor's criteria).
     */
    public function createTransaction(array $data): int
    {
        $filteredData = $this->donorDonationDataFilter->filter($data);
        if (!$this->donorDonationDataValidator->isValid($filteredData)) {
            throw new ValidatorException();
        }

        $donor = $this->repo->getById($filteredData['donorId']);
        if (!$donor) {
            throw new \Exception('Donor not found');
        }

        // project === -1 means "every project"; otherwise the single chosen one.
        $projects = $filteredData['project'] === -1
            ? $this->project->getEntities()
            : array_values(array_filter([$this->project->getById($filteredData['project'])]));

        // Per-payment-type budget in RSD (convert the EUR entries to RSD).
        $budgets = [];
        foreach ($filteredData['paymentData'] as $type => $payment) {
            $rsd = (int) $payment['currency'] === PaymentMethod::CURRENCY_EUR
                ? Transaction::eurToRsd((int) $payment['amount'])
                : (int) $payment['amount'];
            $budgets[(int) $type] = ($budgets[(int) $type] ?? 0) + $rsd;
        }

        return $this->transaction->createForDonor($donor, $projects, $budgets);
    }

}