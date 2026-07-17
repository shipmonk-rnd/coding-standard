<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;
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
 *             trailing `// comments` ride along as token trivia.
 *
 * Choice points (all read off the source, never off line width):
 *   - flat vs broken:   any newline at a joint of THIS level (or a comment row / a
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
        Emitter $e,
        SigToken $open,
        array $items,
        SigToken $close,
        RenderCtx $ctx,
        bool $forceBroken = false,
        bool $onePerRow = false,
    ): void
    {
        $e->token($open);

        if ($items === []) {
            $e->token($close);

            return;
        }

        $broken = $forceBroken || $close->newlinesBefore() > 0;

        foreach ($items as $item) {
            if ($item instanceof CommentRow || $item->firstToken()->newlinesBefore() > 0) {
                $broken = true;
                break;
            }
        }

        if (!$broken) {
            foreach ($items as $i => $item) {
                if ($i > 0) {
                    $e->token($items[$i - 1]->commaToken());
                    $e->space();
                }

                $item->render($e, $ctx);
            }

            // trailing comma is NOT emitted in the flat form (layout comma)
            $e->token($close);

            return;
        }

        $inner = $ctx->line + 1;

        foreach (self::projectRows($items, $onePerRow) as $row) {
            if ($row instanceof CommentRow) {
                if (str_contains($row->token->text, "\n")) {
                    throw new FatalError('multi-line comment inside a collection is not supported', $row->token->line);
                }

                $e->lineBreak($row->token, $inner);
                $e->token($row->token);
                continue;
            }

            $e->newline($inner, $row->blankBefore);

            foreach ($row->items as $i => $item) {
                if ($i > 0) {
                    $e->token($row->items[$i - 1]->commaToken());
                    $e->space();
                }

                $item->render($e, RenderCtx::atLine($inner));
            }

            // every row ends with a comma (trailing comma mandatory when broken);
            // re-emit the source comma when present so its trivia survives
            $last = $row->items[count($row->items) - 1];
            $comma = $last->commaToken();
            $comma !== null ? $e->token($comma) : $e->text(',');
        }

        $e->newline($ctx->line);
        $e->token($close);
    }

    /**
     * Pure projection of the author's row grouping (notes/50 §7).
     *
     * @param non-empty-list<ListItem|CommentRow> $items
     * @return non-empty-list<Row|CommentRow>
     */
    public static function projectRows(array $items, bool $onePerRow): array
    {
        $rows = [];
        $current = [];
        $blank = false;

        foreach ($items as $i => $item) {
            $newlines = $item->firstToken()->newlinesBefore();

            if ($item instanceof CommentRow) {
                if ($i > 0 && $newlines === 0) {
                    throw new FatalError('comment must be on its own line inside a collection', $item->token->line);
                }

                if ($current !== []) {
                    $rows[] = new Row($current, $blank);
                    $current = [];
                }

                $rows[] = $item;
                continue;
            }

            $startsRow = $i === 0 || $onePerRow || $newlines > 0;

            if ($startsRow && $current !== []) {
                $rows[] = new Row($current, $blank);
                $current = [];
            }

            if (!$startsRow && $current === []) {
                // same line as a preceding comment row — no layout in the allowed set
                throw new FatalError('element must start on its own line after a comment', $item->firstToken()->line);
            }

            if ($current === []) {
                $blank = $i > 0 && $newlines >= 2;
            }

            $current[] = $item;
        }

        if ($current !== []) {
            $rows[] = new Row($current, $blank);
        }

        return $rows;
    }

}
