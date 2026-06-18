<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\ControlStructures;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class SwitchStatementToMatchExprSniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/SwitchStatementToMatchExpr/SwitchStatementToMatchExprNoErrors.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/SwitchStatementToMatchExpr/SwitchStatementToMatchExprErrors.php');
        self::assertErrorCount($report, 2);
    }

}
