<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\Whitespaces;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class CatchSpacingSniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/CatchSpacing/CatchSpacingDataPass.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/CatchSpacing/CatchSpacingDataError.php');
        self::assertErrorCount($report, 7);
        self::assertNoErrorInFixedFile($report);
    }

    public function testSpaceAfterCatch(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/CatchSpacing/CatchSpacingDataSpace.php');
        self::assertErrorCount($report, 1);
        self::assertNoErrorInFixedFile($report);
    }

    public function testMultiline(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/CatchSpacing/CatchSpacingDataMultiline.php');
        self::assertErrorCount($report, 7);
    }

    public function testFail(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/CatchSpacing/CatchSpacingDataFail.php');
        self::assertErrorCount($report, 8);
    }

}
