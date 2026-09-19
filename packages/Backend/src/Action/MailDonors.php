<?php

namespace Solidarity\Backend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Mailer\Service\Mailer;

/**
 * Send one announcement (themes/email/donorBroadcast.php) to every donor.
 *
 * Who receives it is DonorRepository::getBroadcastRecipients() — everyone except DELETED and
 * PROBLEM accounts. That is deliberately wider than the allocation pool: a donor we have
 * stopped issuing instructions to is still someone we talk to.
 *
 * One address at a time, and a failure is counted, not thrown: a bounce or a MailerSend
 * hiccup on donor 300 must not stop donors 301+ from getting the mail, and the summary at the
 * end says how many it missed so somebody can look at the log. There is no resume — if the
 * run dies halfway, re-running sends to everybody again — so read the dry-run count first and
 * do not run this twice by accident.
 *
 *   php public/cli.php mailDonors dry                  list who would get it, send nothing
 *   php public/cli.php mailDonors test=you@example.com render and send ONE copy to that
 *                                                      address only — proofread it here
 *   php public/cli.php mailDonors run                  send to everyone
 *
 * Outside production Mailer::send() diverts to Mailpit regardless of the mode, so `run` on a
 * dev box is safe; it is only real on the server.
 */
class MailDonors extends Html
{
    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        private DonorRepository $donors,
        private Mailer $mailer,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        // CliSkeletor passes the argv tail as the "params" attribute.
        $params = (array) $request->getAttribute('params', []);
        $dry = in_array('dry', $params, true);
        $run = in_array('run', $params, true);
        $testAddress = null;
        foreach ($params as $param) {
            if (is_string($param) && str_starts_with($param, 'test=')) {
                $testAddress = trim(substr($param, 5));
            }
        }

        if ($testAddress !== null) {
            if (!filter_var($testAddress, FILTER_VALIDATE_EMAIL)) {
                echo sprintf('"%s" is not an email address.', $testAddress) . PHP_EOL;
                return $response;
            }
            $this->mailer->sendDonorBroadcastMail($testAddress, 'Test Donator');
            echo sprintf('Test copy sent to %s.', $testAddress) . PHP_EOL;
            return $response;
        }

        // Explicit `run`, nothing else, sends. `mailDonors` with a typo in the mode must
        // not go out to the whole base.
        if (!$dry && !$run) {
            echo 'Usage: php public/cli.php mailDonors dry|run|test=<email>' . PHP_EOL;
            return $response;
        }

        $recipients = $this->donors->getBroadcastRecipients();
        echo sprintf(
            '=== MAIL DONORS %s — %s === %d recipient(s)',
            $dry ? 'DRY-RUN' : 'RUN', date('Y-m-d H:i:s'), count($recipients)
        ) . PHP_EOL;

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $donor) {
            if ($dry) {
                echo sprintf("  %-8d %s\n", $donor->id, $donor->email);
                continue;
            }
            try {
                $this->mailer->sendDonorBroadcastMail($donor->email, (string) $donor->getDisplayName());
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $this->getLogger()->error(sprintf(
                    'Broadcast mail failed for donor %d (%s): %s', $donor->id, $donor->email, $e->getMessage()
                ));
                echo sprintf("  FAILED  %-8d %s — %s\n", $donor->id, $donor->email, $e->getMessage());
            }
        }

        if ($dry) {
            echo sprintf('%d would be sent. Nothing was.', count($recipients)) . PHP_EOL;
            return $response;
        }

        $summary = sprintf('%d sent, %d failed.', $sent, $failed);
        echo $summary . PHP_EOL;
        if ($failed) {
            $this->getLogger()->error('mailDonors: ' . $summary . ' See the log for addresses.');
        }

        return $response;
    }
}
