<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Template: `new class(args) extends A implements B { ... }` — header flat, body
 * brace on the SAME line (anonymous-class convention, unlike named ClassDecl),
 * members at +1 with 0-1 blank lines preserved.
 */
final class AnonClassExpr implements Node
{

    /**
     * @param list<ListItem|CommentRow> $args
     * @param list<SigToken> $extends
     * @param list<SigToken> $implements
     * @param list<Node> $members
     */
    public function __construct(
        private readonly SigToken $newKeyword,
        private readonly SigToken $classKeyword,
        private readonly ?SigToken $argsOpen,
        private readonly array $args,
        private readonly ?SigToken $argsClose,
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
        $e->token($this->newKeyword);
        $e->space();
        $e->token($this->classKeyword);

        if ($this->argsOpen !== null) {
            $e->space(); // `new class ($x)` — corpus-unanimous, PER-CS style
            CollectionLayout::render($e, $this->argsOpen, $this->args, $this->argsClose, $ctx);
        }

        $this->nameList($e, ' extends ', $this->extends);
        $this->nameList($e, ' implements ', $this->implements);

        $e->space();
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
        return $this->newKeyword;
    }

}
