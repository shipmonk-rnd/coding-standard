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

    public function render(int $depth): string
    {
        $text = $this->token->text;

        if (!str_contains($text, "\n")) {
            return $text;
        }

        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }

            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '*')) {
                $lines[$i] = Layout::indent($depth) . ' ' . $trimmed;
            }
        }

        return implode("\n", $lines);
    }

    public function firstToken(): SigToken
    {
        return $this->token;
    }

}
