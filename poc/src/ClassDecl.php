<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function implode;

/**
 * Class / interface / trait / enum declaration.
 *
 * Template: header on one line (`final class X extends A implements B`), opening
 * brace on its OWN line (PEAR.Classes.ClassDeclaration style, notes/01), members at
 * depth+1 each on their own line with 0-1 blank lines between them (author's grouping
 * preserved, clamped), closing brace at the declaration's depth with an optional
 * single blank line before it.
 */
final class ClassDecl implements Node
{

    /**
     * @param list<SigToken> $modifiers
     * @param list<SigToken> $extends
     * @param list<SigToken> $implements
     * @param list<Node> $members
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly ?TypeNode $enumBacking,
        private readonly array $extends,
        private readonly array $implements,
        private readonly array $members,
        private readonly SigToken $bodyClose,
    )
    {
    }

    public function render(int $depth): string
    {
        $head = '';

        foreach ($this->modifiers as $modifier) {
            $head .= $modifier->text . ' ';
        }

        $head .= $this->keyword->text . ' ' . $this->name->text;

        if ($this->enumBacking !== null) {
            $head .= ': ' . $this->enumBacking->render($depth);
        }

        if ($this->extends !== []) {
            $head .= ' extends ' . implode(', ', $this->names($this->extends));
        }

        if ($this->implements !== []) {
            $head .= ' implements ' . implode(', ', $this->names($this->implements));
        }

        $out = $head . "\n" . Layout::indent($depth) . '{';

        if ($this->members === []) {
            return $out . "\n" . Layout::indent($depth) . '}';
        }

        return $out
            . StmtSeries::render($this->members, $depth + 1)
            . ($this->bodyClose->newlinesBefore() >= 2 ? "\n" : '')
            . "\n" . Layout::indent($depth) . '}';
    }

    /**
     * @param list<SigToken> $tokens
     * @return list<string>
     */
    private function names(array $tokens): array
    {
        $names = [];

        foreach ($tokens as $token) {
            $names[] = $token->text;
        }

        return $names;
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
