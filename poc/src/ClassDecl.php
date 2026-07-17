<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Class / interface / trait / enum declaration.
 *
 * Template: header on one line (`final class X extends A implements B`), opening
 * brace on its OWN line (PEAR.Classes.ClassDeclaration style, notes/01), members at
 * depth+1 each on their own line with 0-1 blank lines between them (author's grouping
 * preserved, clamped), closing brace at the declaration's depth with an optional
 * single blank line before it. Empty body: both `{\n}` and `{\n\n}` allowed.
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
        private readonly SigToken $bodyOpen,
        private readonly array $members,
        private readonly SigToken $bodyClose,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        foreach ($this->modifiers as $modifier) {
            $e->token($modifier);
            $e->space();
        }

        $e->token($this->keyword);
        $e->space();
        $e->token($this->name);

        if ($this->enumBacking !== null) {
            $e->text(': ');
            $this->enumBacking->render($e, $ctx);
        }

        $this->nameList($e, ' extends ', $this->extends);
        $this->nameList($e, ' implements ', $this->implements);

        $e->newline($ctx->line);
        $e->token($this->bodyOpen);
        StmtSeries::render($e, $this->members, $ctx->line + 1, $this->bodyClose);
        $e->newline($ctx->line, $this->bodyClose->newlinesBefore() >= 2);
        $e->token($this->bodyClose);
    }

    /**
     * @param list<SigToken> $names
     */
    private function nameList(Emitter $e, string $keyword, array $names): void
    {
        foreach ($names as $i => $name) {
            $e->text($i === 0 ? $keyword : ', ');
            $e->token($name);
        }
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
