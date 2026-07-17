<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;

/**
 * Template: `try { ... } catch (A | B $e) { ... } finally { ... }` — `} catch (` on
 * one line, catch types joined ` | ` WITH spaces (existing CatchSpacing standard).
 */
final class TryStmt implements Node
{

    /**
     * @param list<CatchClause> $catches
     */
    public function __construct(
        private readonly SigToken $keyword,
        private readonly Block $block,
        private readonly array $catches,
        private readonly ?Block $finally,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = $this->keyword->text . ' ' . $this->block->render($depth);

        foreach ($this->catches as $catch) {
            $out .= ' ' . $catch->render($depth);
        }

        if ($this->finally !== null) {
            $out .= ' finally ' . $this->finally->render($depth);
        }

        return $out;
    }

    public function firstToken(): SigToken
    {
        return $this->keyword;
    }

}
