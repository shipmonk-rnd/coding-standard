<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * A run of tokens emitted byte-exactly as written (interpolated strings,
 * heredoc/nowdoc) — the ruff `fmt: off` / verbatim-passthrough idea (notes/11).
 */
final class VerbatimSpan implements Node
{

    /**
     * @param non-empty-list<SigToken> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->tokens as $i => $token) {
            $out .= ($i > 0 ? $token->gapBefore : '') . $token->text;
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->tokens[0];
    }

}
