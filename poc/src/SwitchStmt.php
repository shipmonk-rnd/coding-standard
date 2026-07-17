<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template:
 *     switch (subject) {
 *         case expr:
 *             stmts...
 *         default:
 *             stmts...
 *     }
 * Blank lines between cases and between statements preserved 0-1.
 */
final class SwitchStmt implements Node
{

    /**
     * @param list<SwitchCase> $cases
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Cond $subject,
        private readonly array $cases,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->keyword->text . ' ' . $this->subject->render($depth) . ' {';

        foreach ($this->cases as $case) {
            $blank = $case->firstToken()->newlinesBefore() >= 2 ? "\n" : '';
            $out .= "\n" . $blank . Layout::indent($depth + 1) . $case->renderCase($depth + 1);
        }

        return $out . "\n" . Layout::indent($depth) . '}';
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
