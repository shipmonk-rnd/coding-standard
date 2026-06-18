<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\MultilineConditionSpacing;

class MultilineConditionSpacingNoErrors
{

    public function run(bool $a, bool $b, bool $c): bool
    {
        if (
            $a
            && $b
            || $c
        ) {
            return true;
        }

        if ($a && $b) {
            return true;
        }

        return false;
    }

}
