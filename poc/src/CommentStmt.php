<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function explode;
use function implode;
use function ltrim;
use function str_contains;
use function str_starts_with;

/**
 * A comment on its own line. Single-line comments are verbatim; multi-line
 * comments/docblocks get their ` * ` continuation lines re-indented to the
 * current depth (content untouched).
 */
final class CommentStmt implements Node
{

    public function __construct(
        private readonly SigToken $token,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        self::emitReindented($e, $this->token, $ctx->line);
    }

    /**
     * Shared comment writer (comment rows in collections / chains use it too):
     * single-line comments verbatim, docblock ` * ` continuation lines re-indented
     * to $indent, and the token's trailing trivia carried (a `// note` after a docblock).
     */
    public static function emitReindented(Emitter $e, SigToken $token, int $indent): void
    {
        $text = $token->text;

        if (str_contains($text, "\n")) {
            $lines = explode("\n", $text);

            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    continue;
                }

                $trimmed = ltrim($line);

                if (str_starts_with($trimmed, '*')) {
                    $lines[$i] = Layout::indent($indent) . ' ' . $trimmed;
                }
            }

            $text = implode("\n", $lines);
        }

        $e->verbatim($text);
        $e->carryTrivia($token);
    }

    public function firstToken(): SigToken
    {
        return $this->token;
    }

}
