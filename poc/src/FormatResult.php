<?php declare(strict_types = 1);

namespace ShipMonkFmt;

final class FormatResult
{

    public function __construct(
        public readonly string $output,
        public readonly bool $changed,
        public readonly ?string $fatal,
    )
    {
    }

}
