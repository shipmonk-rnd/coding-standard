<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\SwitchStatementToMatchExpr;

class SwitchStatementToMatchExprErrors
{

    public function run(int $x): string
    {
        switch ($x) {
            case 1:
                $result = 'one';
                break;
            default:
                $result = 'other';
        }

        switch ($x) {
            case 2:
                $other = 'two';
                break;
            default:
                $other = 'other';
        }

        return $result . $other;
    }

}
