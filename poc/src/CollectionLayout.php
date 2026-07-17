<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;
use function str_contains;

/**
 * THE core template — shared by array literals, call arguments, parameter lists and
 * closure `use` lists.
 *
 * Allowed-form set (see notes/03, notes/04):
 *
 *   FLAT      `[a, b]`          — one line, `, ` separators, no space inside the
 *                                 delimiters, NO trailing comma
 *   BROKEN    `[` newline, then one or more ROWS, then closer on its own line at the
 *             opener's depth. Each row: one indent level deeper, one OR MORE elements
 *             joined `, `, terminated by a comma (trailing comma required). Rows may
 *             be separated by a single blank line. A comment may form its own row;
 *             a row may end with a trailing `// comment`.
 *
 * Choice points (all read off the source, never off line width):
 *   - flat vs broken:   any newline at a joint of THIS level (or a comment / a
 *                       structural force such as ">= 2 parameters")
 *   - row boundaries:   newline before an element = the author starts a new row
 *                       (unless $onePerRow — declarations mandate one per line)
 *   - blank grouping:   2+ newlines before a row = one blank line (clamped to 1)
 */
final class CollectionLayout
{

    /**
     * @param list<ListItem|CommentRow> $items
     */
    public static function render(
        SigToken $open,
        array $items,
        SigToken $close,
        int $depth,
        bool $forceBroken = false,
        bool $onePerRow = false,
    ): string
    {
        if ($items === []) {
            return $open->text . $close->text;
        }

        $broken = $forceBroken || $close->newlinesBefore() > 0;

        foreach ($items as $item) {
            if (
                $item instanceof CommentRow
                || $item->firstToken()->newlinesBefore() > 0
                || $item->trailingComment() !== null
            ) {
                $broken = true;
                break;
            }
        }

        if (!$broken) {
            $parts = [];

            foreach ($items as $item) {
                $parts[] = $item->render($depth);
            }

            return $open->text . implode(', ', $parts) . $close->text;
        }

        $indent = Layout::indent($depth + 1);
        $out = $open->text . "\n";
        $pendingComment = null; // trailing comment of the still-open row
        $rowOpen = false;

        $closeRow = static function () use (&$out, &$rowOpen, &$pendingComment): void {
            if ($rowOpen) {
                $out .= ',' . ($pendingComment !== null ? ' ' . $pendingComment->text : '') . "\n";
                $rowOpen = false;
                $pendingComment = null;
            }
        };

        foreach ($items as $i => $item) {
            $newlines = $item->firstToken()->newlinesBefore();
            $blank = $i > 0 && $newlines >= 2;

            if ($item instanceof CommentRow) {
                if (str_contains($item->token->text, "\n")) {
                    throw new FatalError('multi-line comment inside a collection is not supported', $item->token->line);
                }

                if ($i > 0 && $newlines === 0) {
                    throw new FatalError('comment must be on its own line inside a collection', $item->token->line);
                }

                $closeRow();
                $out .= ($blank ? "\n" : '') . $indent . $item->token->text . "\n";
                continue;
            }

            if ($i === 0 || $newlines > 0 || $onePerRow) {
                $closeRow();
                $out .= ($blank ? "\n" : '') . $indent;
            } elseif ($rowOpen) {
                $out .= ', ';
            } else {
                // same line as a preceding comment row — no layout in the allowed set
                throw new FatalError('element must start on its own line after a comment', $item->firstToken()->line);
            }

            $out .= $item->render($depth + 1);
            $rowOpen = true;
            $pendingComment = $item->trailingComment();
        }

        $closeRow();

        return $out . Layout::indent($depth) . $close->text;
    }

}
