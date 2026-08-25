<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\EmailList;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\EmailList\Entity\EmailList;
use Solidarity\EmailList\Factory\EmailListFactory;
use Solidarity\EmailList\Filter\EmailList as EmailListFilter;
use Solidarity\EmailList\Validator\EmailList as EmailListValidator;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Support\AssertsFieldCoverage;

/**
 * Posted newsletter-signup data, through the filter and factory, into the database.
 *
 * See PeriodFactoryTest for why the trip starts at $postData.
 */
#[CoversClass(EmailListFactory::class)]
#[CoversClass(EmailListFilter::class)]
final class EmailListFactoryTest extends IntegrationTestCase
{
    use AssertsFieldCoverage;

    private const WRITABLE = [
        'email' => 'donor@example.com',
        'isActive' => 1,
    ];

    /**
     * Where the stored value differs from the posted one, because something casts.
     *
     * `isActive` is a BOOLEAN column behind a `bool` property, but the filter casts to int —
     * so 1 goes in and true comes back. Worth knowing that the same flag on Page is the
     * other way round: `isLoginProtected` is an INTEGER column fed a bool.
     */
    private const STORED = [
        'isActive' => true,
    ];

    /** Nothing here yet — every field on this entity comes from the signup form. */
    private const IGNORED = [];

    public function testEveryMappedFieldIsAccountedFor(): void
    {
        $this->assertEveryMappedFieldIsAccountedFor(EmailList::class, self::WRITABLE, self::IGNORED);
    }

    public function testAPostedSignupIsStoredWithEveryFieldItCarried(): void
    {
        $id = EmailListFactory::compileEntityForCreate($this->filter(), $this->em());

        $this->em()->clear();
        $stored = $this->em()->find(EmailList::class, $id);

        foreach (self::WRITABLE as $field => $posted) {
            $expected = self::STORED[$field] ?? $posted;
            self::assertSame($expected, $stored->$field, $field . ' did not survive the round trip');
        }
    }

    public function testASignupIsActiveUnlessSaidOtherwise(): void
    {
        // The public signup form posts nothing but an address, so the default decides
        // whether anyone actually receives the newsletter.
        $id = EmailListFactory::compileEntityForCreate(
            $this->filter(['isActive' => null], unset: ['isActive']),
            $this->em(),
        );

        $this->em()->clear();

        self::assertTrue($this->em()->find(EmailList::class, $id)->isActive);
    }

    public function testSurroundingWhitespaceIsTrimmedFromTheAddress(): void
    {
        // A pasted address routinely carries a trailing space, and an untrimmed copy is a
        // silent bounce plus a duplicate row that never matches the real one.
        $id = EmailListFactory::compileEntityForCreate($this->filter(['email' => "  donor@example.com \n"]), $this->em());

        $this->em()->clear();

        self::assertSame('donor@example.com', $this->em()->find(EmailList::class, $id)->email);
    }

    /**
     * Runs $postData through the real filter.
     *
     * @param array<string, mixed> $overrides
     * @param string[]             $unset keys to remove entirely, to test the filter's defaults
     * @return array<string, mixed>
     */
    private function filter(array $overrides = [], array $unset = []): array
    {
        $postData = $overrides + self::WRITABLE;
        foreach ($unset as $key) {
            unset($postData[$key]);
        }

        return (new EmailListFilter(new EmailListValidator()))->filter($postData);
    }
}
