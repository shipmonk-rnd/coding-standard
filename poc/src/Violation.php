<?php declare(strict_types = 1);

namespace ShipMonkFmt;

final class Violation
{

    public function __construct(
        public readonly int $line,
        public readonly string $message,
        public readonly int $outOffset = 0,
    )
    {
    }

}
