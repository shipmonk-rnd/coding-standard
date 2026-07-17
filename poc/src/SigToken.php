<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A significant token: any token except pure whitespace.
 *
 * Whitespace is not a token here; it is the GAP (raw whitespace string) attached to
 * the *following* significant token. Templates constrain gaps ("joints"); tokens
 * themselves are always emitted verbatim.
 *
 * Same-line `//`-style comments are NOT in the significant stream: the Lexer attaches
 * them to the preceding token as $trailingComment (trailing trivia, Roslyn/Biome
 * style); the Emitter guarantees they end their line. Own-line comments and
 * multi-line comments stay significant tokens.
 */
final class SigToken
{

    public const EOF = 0;

    public ?SigToken $trailingComment = null;

    public function __construct(
        public readonly int $id,
        public readonly string $text,
        public readonly int $line,
        public readonly string $gapBefore,
        public readonly int $pos,
    )
    {
    }

    public function is(int|string $kind): bool
    {
        return is_int($kind) ? $this->id === $kind : $this->text === $kind;
    }

    public function isComment(): bool
    {
        return $this->id === T_COMMENT || $this->id === T_DOC_COMMENT;
    }

    /**
     * Number of line breaks in the gap before this token — THE observable that
     * projection reads choice points from (0 = same line, 1 = new line, >=2 = blank).
     */
    public function newlinesBefore(): int
    {
        return substr_count($this->gapBefore, "\n");
    }

}
