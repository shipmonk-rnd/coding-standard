<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\MultilineTernary;

class MultilineTernaryErrors
{

    public function run(bool $cond, string $a, string $b): string
    {
        return $cond ?
            $a :
            $b;
    }

}
