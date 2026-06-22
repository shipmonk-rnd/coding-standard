<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\DoubleArrowSpacing;

class DoubleArrowSpacingNoErrors
{

    /**
     * @return array<int|string, mixed>
     */
    public function run(int $x): array
    {
        $list = [1 => 'a', 2 => 'b'];
        $map = ['foo' => 'bar'];

        $fn = static fn (int $y): int => $y + 1;

        $matched = match ($x) {
            1 => 'one',
            default => 'other',
        };

        $multiline = [
            'key' =>
                'value',
        ];

        return [$list, $map, $fn($x), $matched, $multiline];
    }

}
