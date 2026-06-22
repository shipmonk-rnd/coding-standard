<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\OpenParenthesisSpacing;

use Throwable;

class OpenParenthesisSpacingNoErrors
{

    public function run(int $x): void
    {
        if ($x === 1) {
            echo 'one';
        } elseif ($x === 2) {
            echo 'two';
        }

        if (
            $x === 3
        ) {
            echo 'three';
        }

        try {
            echo 'try';
        } catch (Throwable $e) {
            echo 'catch';
        }
    }

}
