<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: match is ALWAYS broken (one arm per line, trailing comma) — the single
 * deliberately canonical-vertical construct (matches mago/Prettier practice; a flat
 * match has no allowed form).
 *
 *     match (subject) {
 *         cond1, cond2 => expr,
 *         default => expr,
 *     }
 */
final class MatchExpr implements Node
{

    /**
     * @param list<MatchArm|CommentRow> $arms
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Node $subject,
        private readonly SigToken $condClose,
        private readonly array $arms,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->keyword->text . ' ' . CondLayout::render($this->subject, $this->condClose, $depth) . ' {';

        foreach ($this->arms as $i => $arm) {
            $blank = $i > 0 && $arm->firstToken()->newlinesBefore() >= 2 ? "\n" : '';

            if ($arm instanceof CommentRow) {
                $out .= "\n" . $blank . Layout::indent($depth + 1) . $arm->token->text;
                continue;
            }

            $out .= "\n" . $blank . Layout::indent($depth + 1) . $arm->render($depth + 1);
        }

        return $out . "\n" . Layout::indent($depth) . '}';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
