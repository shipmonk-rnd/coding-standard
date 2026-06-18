<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\Whitespaces;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class OpenParenthesisSpacingSniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/OpenParenthesisSpacing/OpenParenthesisSpacingNoErrors.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/OpenParenthesisSpacing/OpenParenthesisSpacingErrors.php');
        self::assertErrorCount($report, 3);
        self::assertNoErrorInFixedFile($report);
    }

}
