<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\MultilineTernary;

class MultilineTernaryNoErrors
{

    public function run(bool $cond, string $a, string $b): string
    {
        $singleLine = $cond ? $a : $b;

        $multiLine = $cond
            ? $a
            : $b;

        return $singleLine . $multiLine;
    }

}
