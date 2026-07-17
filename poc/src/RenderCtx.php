<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * The two indentation coordinates of a node (notes/50 §1):
 *
 *   line — indent of the line the node's first token sits on
 *   cont — indent for continuation lines the node itself opens
 *
 * They differ depending on where on its line the node starts: a BinChain after
 * `$x = ` continues one level deeper (atLine), but the same BinChain as the sole
 * content of a broken Cond continues at its own indent (aligned, leading operators
 * under the first operand).
 */
final class RenderCtx
{

    private function __construct(
        public readonly int $line,
        public readonly int $cont,
    )
    {
    }

    /** A node starting a fresh line at $indent; its continuations go one deeper. */
    public static function atLine(int $indent): self
    {
        return new self($indent, $indent + 1);
    }

    /** Cond-style: continuations align with the node's own line. */
    public static function aligned(int $indent): self
    {
        return new self($indent, $indent);
    }

}
