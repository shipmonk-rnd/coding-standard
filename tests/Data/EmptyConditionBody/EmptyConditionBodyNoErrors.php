<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\EmptyConditionBody;

class EmptyConditionBodyNoErrors
{

    public function run(int $x): void
    {
        // Non-empty body
        if ($x === 1) {
            echo 'one';
        }

        // Empty if with comment
        if ($x === 1) {
            // no action needed
        }

        // Empty elseif with comment
        if ($x === 1) {
            echo 'one';
        } elseif ($x === 2) {
            // handled elsewhere
        } else {
            echo 'other';
        }

        // Empty else with comment
        if ($x === 1) {
            echo 'one';
        } else {
            // nothing to do
        }

        // All branches with comments
        if ($x === 1) {
            // skip
        } elseif ($x === 2) {
            // also skip
        } else {
            // also nothing
        }
    }

}
