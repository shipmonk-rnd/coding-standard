<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\Arrays;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class DoubleArrowSpacingSniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/DoubleArrowSpacing/DoubleArrowSpacingNoErrors.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/DoubleArrowSpacing/DoubleArrowSpacingErrors.php');
        self::assertErrorCount($report, 3);
        self::assertNoErrorInFixedFile($report);
    }

}
