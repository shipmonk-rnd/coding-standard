<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\SwitchStatementToMatchExpr;

class SwitchStatementToMatchExprNoErrors
{

    public function run(int $x): string
    {
        return match ($x) {
            1 => 'one',
            2 => 'two',
            default => 'other',
        };
    }

}
