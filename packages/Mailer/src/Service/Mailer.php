<?php
namespace Solidarity\Mailer\Service;

use Laminas\Config\Config;
use League\Plates\Engine;
use MailerSend\Helpers\Builder\Attachment;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface as Logger;

class Mailer extends \Skeletor\Core\Mailer\Service\MailerSendMailer
{
    public function __construct(MailerSend $mail, Config $config, Engine $template)
    {
        parent::__construct($mail, $config, $template);
    }

    /**
     * Only production sends through MailerSend. Everywhere else (development,
     * staging, CLI/cron without APPLICATION_ENV set) the mail is caught via SMTP
     * — Mailpit in dev — so no real email is ever sent outside production.
     */
    protected function send($recipients, $subject, $html)
    {
        if (\Solidarity\Core\Environment::isProduction()) {
            parent::send($recipients, $subject, $html);
            return;
        }

        $this->catchViaSmtp($recipients, $subject, $html);
    }

    /**
     * @param \MailerSend\Helpers\Builder\Recipient[] $recipients
     */
    private function catchViaSmtp($recipients, string $subject, string $html): void
    {
        $smtp = $this->config->mailer->smtp;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp->host;
        $mail->Port = (int) $smtp->port;
        $mail->SMTPAuth = false;
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->config->mailer->from, $this->config->offsetGet('appName'));
        foreach ($recipients as $recipient) {
            $data = $recipient->toArray();
            $mail->addAddress($data['email'], (string) ($data['name'] ?? ''));
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
    }

    public function sendDonorInstructionsMail($email, $name)
    {
        $body = $this->render('donorInstructions', [
            'name' => $name,
            'baseUrl' => $this->config->offsetGet('baseUrl')
        ]);
        $recipients = [
            new Recipient($email, $name),
        ];
        $subject = 'Stigle su ti nove instrukcije za uplatu';

        $this->send($recipients, $subject, $body);
    }


    public function sendDonorRegisteredMail($email, $name, $token)
    {
        $body = $this->render('donorRegistered', [
            'name' => $name,
            'token' => $token,
            'baseUrl' => $this->config->offsetGet('baseUrl')
        ]);
        $recipients = [
            new Recipient($email, $email),
        ];
        $subject = 'Potvrda registracije donatora na Mrežu solidarnosti';

        $this->send($recipients, $subject, $body);
    }

    public function sendDashboardMagicLinkMail(string $email, string $magicLinkUrl, string $displayName): void
    {
        $recipients = [
            new Recipient($email, $email),
        ];

        $body = $this->render('magicLink', [
            'displayName' => $displayName,
            'magicLinkUrl' => $magicLinkUrl,
            'baseUrl' => $this->config->offsetGet('baseUrl')
        ]);
        $subject = "Vaš link za prijavu na Mrežu solidarnosti";

        $this->send($recipients, $subject, $body);
    }

    public function sendDonorLoginMail(string $email, string $displayName, string $token): void
    {
        $baseUrl = $this->config->offsetGet('baseUrl');
        $magicLinkUrl = $baseUrl . '/donor/verifyEmail?token=' . $token;

        $body = $this->render('magicLink', [
            'displayName' => $displayName,
            'loginUrl' => $magicLinkUrl, // template reads $data['loginUrl']
            'baseUrl' => $baseUrl,
        ]);

        $this->send([new Recipient($email, $email)], 'Vaš link za prijavu na Mrežu solidarnosti', $body);
    }

}