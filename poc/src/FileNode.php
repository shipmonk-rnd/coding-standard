<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `<?php` header, then each statement on its own line at depth 0, separated
 * by a newline with an optional single blank line (author's blank grouping preserved,
 * runs of 2+ blank lines clamped to 1), single trailing newline at EOF.
 *
 * Allowed exception: `declare(...)` may sit on the same line as `<?php` (the shipmonk
 * declare-on-first-line style) — both forms are in the allowed set.
 */
final class FileNode implements Node
{

    /**
     * @param list<Node> $stmts
     */
    public function __construct(
        private readonly SigToken $openTag,
        private readonly array $stmts,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->openTag->text;

        if ($this->stmts === []) {
            return $out . "\n";
        }

        foreach ($this->stmts as $i => $stmt) {
            $newlines = $stmt->firstToken()->newlinesBefore();

            if ($i === 0 && $stmt instanceof DeclareStmt && $newlines === 0) {
                $out .= ' ';
            } else {
                $out .= "\n" . ($newlines >= 2 ? "\n" : '');
            }

            $out .= $stmt->render(0);
        }

        return $out . "\n";
    }

    public function firstToken(): SigToken
    {
        return $this->openTag;
    }

}
