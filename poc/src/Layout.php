<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function str_repeat;

final class Layout
{

    public const INDENT = '    ';

    public static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }

}
