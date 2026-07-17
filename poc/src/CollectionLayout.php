<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;
use function str_contains;

/**
 * THE core template of the PoC — shared by array literals and call argument lists.
 *
 * Allowed-form set (see notes/03, notes/04):
 *
 *   FLAT      `[a, b]`          — everything on one line, `, ` separators, no space
 *                                 inside the delimiters, NO trailing comma
 *   BROKEN    `[` newline, then one or more ROWS, then closer on its own line at the
 *             opener's depth. Each row: one indent level deeper, one OR MORE elements
 *             joined `, `, terminated by a comma (trailing comma required). Rows may
 *             be separated by a single blank line. A comment may form its own row.
 *
 * Choice points (all read off the source, never off line width):
 *   - flat vs broken:   any newline at a joint of THIS level
 *   - row boundaries:   newline before an element = the author starts a new row
 *   - blank grouping:   2+ newlines before a row = one blank line (clamped to 1)
 *
 * Everything else is mandated and repaired: horizontal spacing, indentation,
 * trailing comma presence (iff broken).
 */
final class CollectionLayout
{

    /**
     * @param list<ArrayItem|CommentRow> $items
     */
    public static function render(SigToken $open, array $items, SigToken $close, int $depth): string
    {
        if ($items === []) {
            return $open->text . $close->text;
        }

        $broken = $close->newlinesBefore() > 0;

        foreach ($items as $item) {
            if ($item instanceof CommentRow || $item->firstToken()->newlinesBefore() > 0) {
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
        $rowOpen = false; // an element row awaiting its terminating ",\n"

        foreach ($items as $i => $item) {
            $newlines = $i === 0 ? 1 : $item->firstToken()->newlinesBefore();
            $blank = $i > 0 && $newlines >= 2;

            if ($item instanceof CommentRow) {
                if (str_contains($item->token->text, "\n")) {
                    throw new FatalError('multi-line comment inside a collection is not supported', $item->token->line);
                }

                if ($i > 0 && $newlines === 0) {
                    throw new FatalError('comment must be on its own line inside a collection', $item->token->line);
                }

                if ($rowOpen) {
                    $out .= ",\n";
                    $rowOpen = false;
                }

                $out .= ($blank ? "\n" : '') . $indent . $item->token->text . "\n";
                continue;
            }

            if ($newlines > 0) {
                if ($rowOpen) {
                    $out .= ",\n";
                }

                $out .= ($blank ? "\n" : '') . $indent;
            } elseif ($rowOpen) {
                $out .= ', ';
            } else {
                // same line as a preceding comment row — no layout in the allowed set
                throw new FatalError('element must start on its own line after a comment', $item->firstToken()->line);
            }

            $out .= $item->render($depth + 1);
            $rowOpen = true;
        }

        if ($rowOpen) {
            $out .= ",\n";
        }

        return $out . Layout::indent($depth) . $close->text;
    }

}
