<?php declare(strict_types = 1);

namespace ShipMonkFmt;

final class FormatResult
{

    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public readonly string $output,
        public readonly bool $changed,
        public readonly ?string $fatal,
        public readonly array $violations = [],
    )
    {
    }

}
