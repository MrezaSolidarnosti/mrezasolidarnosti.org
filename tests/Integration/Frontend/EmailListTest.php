<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session as UserSession;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\EmailList\Entity\EmailList as EmailListEntity;
use Solidarity\EmailList\Filter\EmailList as EmailListFilter;
use Solidarity\EmailList\Repository\EmailListRepository;
use Solidarity\EmailList\Service\EmailList as EmailListService;
use Solidarity\EmailList\Validator\EmailList as EmailListValidator;
use Solidarity\Frontend\Action\EmailList;

/**
 * The newsletter signup behind "Stay in touch with MS".
 *
 * It is the only unauthenticated write endpoint on the site, so the interesting cases are
 * the ones a form never sends: a malformed address, a resubmission, and a resubscribe
 * after someone opted out.
 */
#[CoversClass(EmailList::class)]
final class EmailListTest extends FrontendActionTestCase
{
    public function testAValidAddressIsSubscribed(): void
    {
        $response = $this->action()($this->post(['email' => 'donor@example.com']), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertTrue($this->subscription('donor@example.com')?->isActive);
    }

    public function testAMalformedAddressIsRejected(): void
    {
        $response = $this->action()($this->post(['email' => 'not-an-email']), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertContains('Email is not valid', $this->errorsFrom($response));
        self::assertNull($this->subscription('not-an-email'));
    }

    public function testAWhitespaceOnlyAddressIsRejected(): void
    {
        $response = $this->action()($this->post(['email' => '   ']), $this->emptyResponse());

        self::assertSame(400, $response->getStatusCode());
    }

    public function testSubscribingTwiceDoesNotCreateASecondRow(): void
    {
        // The form is easy to double-submit, and email is unique — a second insert would
        // be a 500 rather than a no-op.
        $this->action()($this->post(['email' => 'donor@example.com']), $this->emptyResponse());
        $response = $this->action()($this->post(['email' => 'donor@example.com']), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(1, $this->em()->getRepository(EmailListEntity::class)->findBy(['email' => 'donor@example.com']));
    }

    public function testResubscribingReactivatesAnOptedOutAddress(): void
    {
        // Unsubscribing flips isActive rather than deleting, so signing up again has to
        // flip it back instead of silently doing nothing.
        $this->action()($this->post(['email' => 'donor@example.com']), $this->emptyResponse());
        $this->deactivate('donor@example.com');

        $this->action()($this->post(['email' => 'donor@example.com']), $this->emptyResponse());

        self::assertTrue($this->subscription('donor@example.com')?->isActive);
    }

    public function testAnEmptyBodyIsAcceptedWithoutWriting(): void
    {
        // The action guards on !empty($data), so a bodyless request is a no-op rather
        // than an undefined-index fatal.
        $response = $this->action()($this->emptyPost(), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertCount(0, $this->em()->getRepository(EmailListEntity::class)->findAll());
    }

    // ---- helpers ------------------------------------------------------------

    private function emptyPost(): \GuzzleHttp\Psr7\ServerRequest
    {
        return (new \GuzzleHttp\Psr7\ServerRequest('POST', '/emailList'))->withParsedBody([]);
    }

    private function subscription(string $email): ?EmailListEntity
    {
        $this->em()->clear();

        return $this->em()->getRepository(EmailListEntity::class)->findOneBy(['email' => $email]);
    }

    private function deactivate(string $email): void
    {
        $this->em()->getConnection()->executeStatement(
            'UPDATE `email_list` SET isActive = 0 WHERE email = :email',
            ['email' => $email],
        );
        $this->em()->clear();
    }

    private function action(): EmailList
    {
        $em = $this->em();

        return new EmailList(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $this->createStub(DonorService::class),
            $this->navigation(),
            $this->socialLinks(),
            $this->session(),
            new EmailListService(
                new EmailListRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                new EmailListFilter(new EmailListValidator()),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
