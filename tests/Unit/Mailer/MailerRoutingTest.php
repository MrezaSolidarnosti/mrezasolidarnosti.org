<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Mailer;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use MailerSend\Endpoints\Email;
use MailerSend\Helpers\Builder\EmailParams;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solidarity\Mailer\Service\Mailer;

/**
 * Which way does the mail go.
 *
 * The whole of Mailer::send() is one branch: production goes out through MailerSend to real
 * people, everything else is caught over SMTP by Mailpit. Getting that backwards on a
 * staging box means mailing every donor in the database, and the guard is a single
 * getenv() — so it is worth pinning from both sides, including the cases where the variable
 * is missing or oddly cased.
 *
 * The SMTP half used to be untestable because catchViaSmtp() constructed its own PHPMailer
 * and opened a socket. Mailer now takes an optional transport factory for exactly this.
 */
#[CoversClass(Mailer::class)]
final class MailerRoutingTest extends TestCase
{
    private const APP_NAME = 'Mreža Solidarnosti';
    private const BASE_URL = 'https://solidarity.local';
    private const FROM = 'noreply@mrezasolidarnosti.org';

    private string|false $envBackup = false;

    /** @var list<array{string, array<string, mixed>}> template name + data, in render order */
    private array $rendered = [];

    /** @var list<array{string, array<int, mixed>}> method name + arguments, in call order */
    private array $smtpCalls = [];

    /** The transport from the most recent send; tests that send once assert against it. */
    private ?PHPMailer $smtp = null;

    /** The params handed to MailerSend, or null when the SMTP branch was taken. */
    private ?EmailParams $sent = null;

    protected function setUp(): void
    {
        $this->envBackup = getenv('APPLICATION_ENV');
    }

    protected function tearDown(): void
    {
        // Environment reads getenv() on every call precisely so a test can do this, but the
        // variable is process-wide — leaving it set would decide the branch for every test
        // that runs after this file.
        if ($this->envBackup === false) {
            putenv('APPLICATION_ENV');
        } else {
            putenv('APPLICATION_ENV=' . $this->envBackup);
        }
    }

    // ---- the branch -------------------------------------------------------------

    public function testProductionSendsThroughMailerSendAndOpensNoSmtpConnection(): void
    {
        $mailer = $this->mailer('production', expectSmtp: false, expectMailerSend: true);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertNotNull($this->sent);
        self::assertSame([], $this->smtpCalls);
    }

    #[DataProvider('nonProductionEnvironments')]
    public function testEverythingOtherThanProductionIsCaughtOverSmtp(?string $environment): void
    {
        $mailer = $this->mailer($environment, expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertNull($this->sent, 'MailerSend must not be reached outside production');
        self::assertCount(1, $this->smtpArgs('isSMTP'));
    }

    /** @return array<string, array{?string}> */
    public static function nonProductionEnvironments(): array
    {
        return [
            'development' => ['development'],
            // The reason the guard is a whitelist of one rather than "is it development".
            'staging' => ['staging'],
            // Cron and CLI runs: crontab exports APPLICATION_ENV, but a command run by hand
            // does not, and an unset variable must never be read as production.
            'unset' => [null],
            'empty string' => [''],
            'a typo nobody notices' => ['prod'],
        ];
    }

    public function testTheEnvironmentNameIsMatchedCaseInsensitively(): void
    {
        // Environment::name() lower-cases before comparing, so a vhost configured with
        // "Production" still sends for real. Asserting it here keeps that from being
        // "simplified" into a strict comparison against the raw variable.
        $mailer = $this->mailer('PRODUCTION', expectSmtp: false, expectMailerSend: true);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertNotNull($this->sent);
    }

    // ---- what the caught mail looks like ------------------------------------------

    public function testTheCaughtMailGoesToTheConfiguredSmtpHostAndPort(): void
    {
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertSame('127.0.0.1', $this->smtp->Host);
        self::assertSame(1025, $this->smtp->Port);
        // Mailpit accepts anything; asking for auth would just fail the handshake.
        self::assertFalse($this->smtp->SMTPAuth);
    }

    public function testTheCaughtMailKeepsTheSubjectAndBodyAndCarriesAPlainTextAlternative(): void
    {
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertSame('Stigle su ti nove instrukcije za uplatu', $this->smtp->Subject);
        self::assertSame($this->renderedHtml('donorInstructions'), $this->smtp->Body);
        self::assertSame(strip_tags($this->smtp->Body), $this->smtp->AltBody);
        self::assertSame(PHPMailer::CHARSET_UTF8, $this->smtp->CharSet);
    }

    public function testTheCaughtMailIsSentFromTheConfiguredAddressUnderTheAppName(): void
    {
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        // Only the first two arguments: PHPMailer::setFrom() declares a third ($auto) with
        // a default, and a mock records the defaults alongside what the caller passed.
        self::assertSame(
            [self::FROM, self::APP_NAME],
            array_slice($this->smtpArgs('setFrom')[0], 0, 2),
        );
    }

    public function testEveryRecipientIsAddedToTheCaughtMail(): void
    {
        // Nothing public sends to more than one address today, so this goes at send()
        // directly: the loop is what a future multi-recipient mail would rely on, and a
        // "first recipient only" regression would be invisible from the outside.
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        (new \ReflectionMethod($mailer, 'send'))->invoke($mailer, [
            new Recipient('one@example.com', 'One'),
            new Recipient('two@example.com', 'Two'),
        ], 'Subject', '<p>Body</p>');

        self::assertSame(
            [['one@example.com', 'One'], ['two@example.com', 'Two']],
            $this->smtpArgs('addAddress'),
        );
    }

    // ---- what the production mail looks like -----------------------------------------

    public function testTheProductionMailCarriesTheSenderSubjectBodyAndRecipient(): void
    {
        $mailer = $this->mailer('production', expectSmtp: false, expectMailerSend: true);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertSame(self::FROM, $this->sent->getFrom());
        self::assertSame(self::APP_NAME, $this->sent->getFromName());
        self::assertSame('Stigle su ti nove instrukcije za uplatu', $this->sent->getSubject());
        self::assertSame($this->renderedHtml('donorInstructions'), $this->sent->getHtml());
        // assertEquals, not assertSame: Recipient::toArray() returns name before email, and
        // pinning the SDK's key order here would buy nothing.
        self::assertEquals(
            [['email' => 'donor@example.com', 'name' => 'Petar']],
            array_map(static fn (Recipient $r): array => $r->toArray(), $this->sent->getRecipients()),
        );
    }

    public function testRepliesGoBackToTheSendingAddressRatherThanNowhere(): void
    {
        $mailer = $this->mailer('production', expectSmtp: false, expectMailerSend: true);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');

        self::assertSame(self::FROM, $this->sent->getReplyTo());
        self::assertSame(self::APP_NAME, $this->sent->getReplyToName());
    }

    // ---- what each mail puts in front of the donor -----------------------------------

    public function testTheDonorLoginMailBuildsAVerifyEmailLinkFromTheTokenAndBaseUrl(): void
    {
        // magicLink.php renders $data['loginUrl'] into the href and nothing else, so a
        // renamed key or a missing baseUrl produces a login button that goes nowhere —
        // and the mail still sends, so nothing surfaces until a donor clicks it.
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorLoginMail('donor@example.com', 'Petar', 'the-token');

        self::assertSame(
            self::BASE_URL . '/donor/verifyEmail?token=the-token',
            $this->renderedData('magicLink')['loginUrl'] ?? null,
        );
    }

    public function testTheRegistrationMailCarriesTheVerificationToken(): void
    {
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorRegisteredMail('donor@example.com', 'Petar', 'the-token');

        self::assertSame('the-token', $this->renderedData('donorRegistered')['token'] ?? null);
        self::assertSame('Potvrda registracije donatora na Mrežu solidarnosti', $this->smtp->Subject);
    }

    public function testEveryTemplateIsGivenTheBaseUrlItNeedsForItsAssetsAndLinks(): void
    {
        $mailer = $this->mailer('development', expectSmtp: true, expectMailerSend: false);

        $mailer->sendDonorInstructionsMail('donor@example.com', 'Petar');
        $mailer->sendDonorRegisteredMail('donor@example.com', 'Petar', 'token');
        $mailer->sendDonorLoginMail('donor@example.com', 'Petar', 'token');

        foreach ($this->rendered as [$template, $data]) {
            self::assertSame(self::BASE_URL, $data['baseUrl'] ?? null, $template . ' has no baseUrl');
        }
    }

    // ---- collaborators ------------------------------------------------------------------

    /**
     * @param string|null $environment value for APPLICATION_ENV; null unsets it
     * @param bool        $expectSmtp  whether the SMTP transport should be used exactly once
     */
    private function mailer(?string $environment, bool $expectSmtp, bool $expectMailerSend): Mailer
    {
        if ($environment === null) {
            putenv('APPLICATION_ENV');
        } else {
            putenv('APPLICATION_ENV=' . $environment);
        }

        return new Mailer(
            $this->mailerSend($expectMailerSend),
            new Config([
                'appName' => self::APP_NAME,
                'baseUrl' => self::BASE_URL,
                'mailer' => [
                    'from' => self::FROM,
                    'smtp' => ['host' => '127.0.0.1', 'port' => 1025],
                ],
            ]),
            $this->engine(),
            fn (): PHPMailer => $this->smtpTransport($expectSmtp),
        );
    }

    private function smtpTransport(bool $expectUse): PHPMailer
    {
        // A mock rather than a stub: send() being called (or not) is the assertion, and a
        // real PHPMailer here would try to reach Mailpit from the test runner.
        $smtp = $this->createMock(PHPMailer::class);
        $smtp->expects($expectUse ? self::once() : self::never())->method('send');

        foreach (['isSMTP', 'setFrom', 'addAddress', 'isHTML'] as $method) {
            $smtp->method($method)->willReturnCallback(
                fn (...$args) => $this->recordSmtp($method, $args),
            );
        }

        return $this->smtp = $smtp;
    }

    private function recordSmtp(string $method, array $args): bool
    {
        $this->smtpCalls[] = [$method, $args];

        return true;
    }

    /**
     * The argument lists of every call to $method, in order. A mock passes each declared
     * parameter through, so calls carry the arguments the caller omitted as well.
     *
     * @return list<array<int, mixed>>
     */
    private function smtpArgs(string $method): array
    {
        $calls = [];
        foreach ($this->smtpCalls as [$name, $args]) {
            if ($name === $method) {
                $calls[] = $args;
            }
        }

        return $calls;
    }

    private function mailerSend(bool $expectUse): MailerSend
    {
        $email = $this->createMock(Email::class);
        $email->expects($expectUse ? self::once() : self::never())
            ->method('send')
            ->willReturnCallback(function (EmailParams $params): array {
                $this->sent = $params;

                // MailerSendMailer::send() var_dumps and die()s on anything else — a stub
                // returning the default [] would kill the test process, not fail the test.
                return ['status_code' => 202];
            });

        $mailerSend = $this->createStub(MailerSend::class);
        // createStub skips the constructor, so the endpoint properties are uninitialised
        // rather than absent; they are public and untouched by readonly, so this sticks.
        $mailerSend->email = $email;

        return $mailerSend;
    }

    private function engine(): Engine
    {
        // Rendering the real theme would drag in the layout, the Translator and the asset
        // constants; what matters here is which template each method picks and what data it
        // hands over, so record that and return a recognisable body.
        $engine = $this->createStub(Engine::class);
        $engine->method('exists')->willReturn(true);
        $engine->method('render')->willReturnCallback(function (string $name, array $data): string {
            $this->rendered[] = [$name, $data['data'] ?? []];

            return $this->renderedHtml($name);
        });

        return $engine;
    }

    private function renderedHtml(string $template): string
    {
        return '<p>rendered ' . $template . '</p>';
    }

    /** @return array<string, mixed> the data the named template was rendered with */
    private function renderedData(string $template): array
    {
        foreach ($this->rendered as [$name, $data]) {
            if ($name === $template) {
                return $data;
            }
        }

        self::fail(sprintf('Template "%s" was never rendered.', $template));
    }
}
