<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * Safety gate (CSharpier's idea, notes/15): the output must contain exactly the same
 * significant tokens as the input — the only allowed difference is whitespace and
 * a trailing comma added/removed directly before a closing `]` / `)`.
 * Any other difference means the formatter has a bug; the caller then discards the
 * output and returns the input unchanged.
 */
final class Verifier
{

    public static function verify(string $input, string $output): void
    {
        $a = Lexer::tokenize($input);
        $b = Lexer::tokenize($output);
        $i = 0;
        $j = 0;

        while ($i < count($a) || $j < count($b)) {
            $ta = $a[$i] ?? null;
            $tb = $b[$j] ?? null;

            if ($ta !== null && $tb !== null && $ta->id === $tb->id && self::sameText($ta, $tb)) {
                $i++;
                $j++;
                continue;
            }

            // trailing comma removed (input has one before a closer, output does not)
            if ($ta !== null && $ta->is(',') && $tb !== null && ($tb->is(']') || $tb->is(')'))) {
                $i++;
                continue;
            }

            // trailing comma added (output has one before a closer, input does not)
            if ($tb !== null && $tb->is(',') && $ta !== null && ($ta->is(']') || $ta->is(')'))) {
                $j++;
                continue;
            }

            throw new FatalError(
                'internal error: verification failed — token stream changed near "' . ($ta->text ?? 'EOF') . '"',
                $ta->line ?? $tb->line ?? 0,
            );
        }
    }

    /**
     * Comments are compared modulo per-line leading whitespace: the formatter is
     * allowed to re-indent multi-line comment/docblock continuation lines, nothing else.
     */
    private static function sameText(SigToken $a, SigToken $b): bool
    {
        if ($a->text === $b->text) {
            return true;
        }

        if (!$a->isComment()) {
            return false;
        }

        return preg_replace('~\n[ \t]*~', "\n", $a->text) === preg_replace('~\n[ \t]*~', "\n", $b->text);
    }

}
