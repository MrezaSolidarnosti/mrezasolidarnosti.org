<?php

namespace Solidarity\Backend\Action;

use Psr\Log\LoggerInterface as Logger;
use Laminas\Config\Config;
use \League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Donor\Service\Donor;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Service\Project;

class CreateTransaction extends Html
{
    public function __construct(
        Logger $logger, Config $config, Engine $template,
        public readonly TransactionService $transaction,
        public readonly Project $project,
        public readonly Donor $donor,
        private Mailer $mailer
    ) {
        parent::__construct($logger, $config, $template);
    }

    /**
     * Allocate every active donor's pledges to beneficiaries. Each donor is balanced across
     * the projects they pledged to (round-robin), with each project keeping its own pledge;
     * the heavy lifting lives in TransactionService::createBalancedForDonor().
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request request
     * @param \Psr\Http\Message\ResponseInterface $response response
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        if ($this->isHoliday()) {
            $this->getLogger()->log(\Monolog\Level::Info, 'Holiday detected. Not creating transactions.');
            return $response;
        }

        $projects = [];
        foreach ($this->project->getEntities([], 2) as $project) {
            $projects[] = $project;
        }

        // Unique active donors across all projects. Each donor is then balanced across the
        // projects they pledged to (round-robin), with every project keeping its own pledge.
        $donors = [];
        foreach ($projects as $project) {
            foreach ($this->donor->getDonorsByProject($project) as $donor) {
                $donors[$donor->id] = $donor;
            }
        }

        foreach ($donors as $donor) {
            $this->getLogger()->log(\Monolog\Level::Info, sprintf('Processing donor %s at %s', $donor->email, date('Y-m-d H:i:s')));
            $this->transaction->createBalancedForDonor($donor, $projects);
            $this->mailer->sendDonorInstructionsMail($donor->email, $donor->getDisplayName());
        }

        echo 1;
        exit();
    }

    public function isHoliday(): bool
    {
        $dates = ['01.01', '02.01', '06.01', '07.01', '15.01', '16.01', '17.01', '20.01', '01.05', '02.05', '06.05', '06.12', '11.11', '25.12', '31.12'];

        return in_array(date('d.m'), $dates);
    }

}
