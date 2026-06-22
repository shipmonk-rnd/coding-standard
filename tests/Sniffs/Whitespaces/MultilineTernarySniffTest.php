<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\Whitespaces;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class MultilineTernarySniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/MultilineTernary/MultilineTernaryNoErrors.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/MultilineTernary/MultilineTernaryErrors.php');
        self::assertErrorCount($report, 2);
        self::assertNoErrorInFixedFile($report);
    }

}
