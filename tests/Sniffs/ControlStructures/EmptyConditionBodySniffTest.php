<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\ControlStructures;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class EmptyConditionBodySniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/EmptyConditionBody/EmptyConditionBodyNoErrors.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/EmptyConditionBody/EmptyConditionBodyErrors.php');
        self::assertErrorCount($report, 6);
    }

}
