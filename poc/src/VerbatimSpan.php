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

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        foreach ($this->tokens as $i => $token) {
            if ($i > 0) {
                $e->verbatim($token->gapBefore);
            }

            $e->token($token);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->tokens[0];
    }

}
