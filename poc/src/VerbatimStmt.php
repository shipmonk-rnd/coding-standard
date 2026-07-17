<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function strlen;
use function strrpos;
use function substr;

/**
 * A statement the parser could not recognize, preserved byte-exactly (error
 * recovery, notes/50 §4): its own-line leading whitespace, interior gaps, and
 * trailing trivia are all emitted verbatim. Reported as a Violation at parse time.
 */
final class VerbatimStmt implements Node
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
        // original indentation of the statement's first line
        $gap = $this->tokens[0]->gapBefore;
        $lastNl = strrpos($gap, "\n");
        $e->verbatim($lastNl === false ? $gap : substr($gap, $lastNl + 1));

        foreach ($this->tokens as $i => $token) {
            if ($i > 0) {
                $e->verbatim($token->gapBefore);
            }

            $e->verbatim($token->text);

            if ($token->trailingComment !== null) {
                $e->verbatim($token->trailingComment->gapBefore . $token->trailingComment->text);
            }
        }
    }

    public function firstToken(): SigToken
    {
        return $this->tokens[0];
    }

}
