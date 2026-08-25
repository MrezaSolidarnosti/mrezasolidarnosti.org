<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use Skeletor\Core\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solidarity\Backend\Controller\DonorController;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Transaction\Service\Project as ProjectService;
use Tamtamchik\SimpleFlash\Flash;

#[CoversClass(DonorController::class)]
final class DonorControllerTest extends TestCase
{
    #[DataProvider('amountProvider')]
    public function testNormalizeAmountParsesLocalisedInput(string $input, int $expected): void
    {
        self::assertSame($expected, $this->controller()->normalizeAmount($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function amountProvider(): array
    {
        return [
            'plain integer'             => ['5000', 5000],
            'no separators'             => ['1234', 1234],
            'thousand sep + decimals'   => ['1.234,56', 1234],
            'multiple thousand seps'    => ['1.234.567,89', 1234567],
            'currency symbol stripped'  => ['€ 5.000,00', 5000],
        ];
    }

    private function controller(): DonorController
    {
        $storage = new ArrayStorage(['loggedIn' => 1]);
        $session = $this->createStub(SessionManager::class);
        $session->method('getStorage')->willReturn($storage);

        return new DonorController(
            $this->createStub(DonorService::class),
            $session,
            new Config(['adminPath' => '']),
            $this->createStub(Flash::class),
            new Engine(),
            $this->createStub(ProjectService::class),
            $this->createStub(\Solidarity\Backend\Service\Redaction::class),
        );
    }
}
