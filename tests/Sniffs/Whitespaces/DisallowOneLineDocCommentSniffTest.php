<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Sniffs\Whitespaces;

use ShipMonkTests\CodingStandard\SniffTestCase;

final class DisallowOneLineDocCommentSniffTest extends SniffTestCase
{

    public function testNoErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/DisallowOneLineDocComment/DisallowOneLineDocCommentDataPass.php');
        self::assertErrorCount($report, 0);
    }

    public function testErrors(): void
    {
        $report = self::checkFile(__DIR__ . '/../../Data/DisallowOneLineDocComment/DisallowOneLineDocCommentDataError.php');
        self::assertErrorCount($report, 4);
        self::assertNoErrorInFixedFile($report);
    }

}
