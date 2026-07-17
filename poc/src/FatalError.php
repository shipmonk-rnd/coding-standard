<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use Exception;

/**
 * A "won't fix" outcome: the input contains a construct or token placement that no
 * template covers. The file is left byte-identical; the error is reported.
 *
 * Design note (notes/04): a FatalError is a loud coverage statement, never a silent
 * allow. Every fatal is either a deliberate "unsupported" or a bug to fix by adding
 * a template.
 */
final class FatalError extends Exception
{

    public function __construct(
        string $message,
        public readonly int $sourceLine,
    )
    {
        parent::__construct($message . ' on line ' . $sourceLine);
    }

}
