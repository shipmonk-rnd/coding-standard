<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;
use function max;
use function preg_replace;

/**
 * Safety gate (CSharpier's idea, notes/15): the output must contain exactly the same
 * significant tokens as the input.
 *
 * Model (notes/50 §8): both token streams are run through the SAME normalization —
 * named, symmetric rules — and must then be exactly equal. No pairwise skip logic.
 *
 * Normalization rules:
 *   - layout commas are deleted: a `,` directly before a closer (`)`, `]`, `}`) or
 *     before `=>` (match condition lists) is a pure layout artifact the formatter
 *     may add or remove
 *   - comments compare modulo per-line leading whitespace (the formatter re-indents
 *     multi-line comment/docblock continuation lines, nothing else)
 */
final class Verifier
{

    public static function verify(string $input, string $output): void
    {
        $a = self::normalize(Lexer::tokenize($input));
        $b = self::normalize(Lexer::tokenize($output));
        $count = max(count($a), count($b));

        for ($i = 0; $i < $count; $i++) {
            $ta = $a[$i] ?? null;
            $tb = $b[$i] ?? null;

            if ($ta === null || $tb === null || $ta[0] !== $tb[0] || $ta[1] !== $tb[1]) {
                throw new FatalError(
                    'internal error: verification failed — token stream changed near "' . ($ta[1] ?? $tb[1] ?? 'EOF') . '"',
                    $ta[2] ?? $tb[2] ?? 0,
                );
            }
        }
    }

    /**
     * @param list<SigToken> $tokens
     * @return list<array{int, string, int}> id, normalized text, line
     */
    private static function normalize(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $i => $token) {
            if ($token->id === SigToken::EOF) {
                continue;
            }

            if ($token->is(',') && self::isLayoutComma($tokens[$i + 1] ?? null)) {
                // the comma vanishes, but its trailing comment is real content:
                // `[] // note` (comment on `]`) vs the repaired `[], // note`
                // (comment on the synthesized comma) must still compare equal
                if ($token->trailingComment !== null) {
                    $comment = $token->trailingComment;
                    $result[] = [$comment->id, $comment->text, $comment->line];
                }

                continue;
            }

            $text = $token->isComment()
                ? preg_replace('~\n[ \t]*~', "\n", $token->text)
                : $token->text;

            $result[] = [$token->id, $text, $token->line];

            // trailing trivia left the significant stream — re-insert for comparison
            if ($token->trailingComment !== null) {
                $comment = $token->trailingComment;
                $result[] = [$comment->id, $comment->text, $comment->line];
            }
        }

        return $result;
    }

    private static function isLayoutComma(?SigToken $next): bool
    {
        return $next !== null
            && ($next->is(')') || $next->is(']') || $next->is('}') || $next->is(T_DOUBLE_ARROW));
    }

}
