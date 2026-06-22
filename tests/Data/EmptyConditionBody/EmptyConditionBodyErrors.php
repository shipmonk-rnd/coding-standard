<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\EmptyConditionBody;

class EmptyConditionBodyErrors
{

    public function run(int $x): void
    {
        if ($x === 1) {
        }

        if ($x === 1) {
            echo 'one';
        } elseif ($x === 2) {
        } else {
            echo 'other';
        }

        if ($x === 1) {
            echo 'one';
        } else {
        }

        if ($x === 1) {
        } elseif ($x === 2) {
        } else {
        }
    }

}
