<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;

/**
 * Named function / method declaration.
 *
 * Template: `[modifiers] function name(params)[: type]` with the body brace on its
 * OWN line (Allman, per OpeningFunctionBraceBsdAllman — notes/01), or `;` for
 * abstract/interface methods.
 *
 * Structural trigger (count-based, NOT width-based — notes/02): 2+ parameters force
 * the parameter list broken, one parameter per line (RequireMultiLineMethodSignature
 * minParametersCount=2 + one-per-line declaration convention).
 */
final class FunctionDecl implements Node
{

    /**
     * @param list<SigToken> $modifiers
     * @param list<ListItem|CommentRow> $params
     */
    public function __construct(
        private readonly array $modifiers,
        private readonly SigToken $keyword,
        private readonly SigToken $name,
        private readonly SigToken $paramsOpen,
        private readonly array $params,
        private readonly SigToken $paramsClose,
        private readonly ?TypeNode $returnType,
        private readonly ?Block $body,
        private readonly ?SigToken $headerComment = null,
    )
    {
    }

    public function render(int $depth): string
    {
        $out = '';

        foreach ($this->modifiers as $modifier) {
            $out .= $modifier->text . ' ';
        }

        $out .= $this->keyword->text . ' ' . $this->name->text;
        $out .= CollectionLayout::render(
            $this->paramsOpen,
            $this->params,
            $this->paramsClose,
            $depth,
            forceBroken: count($this->params) >= 2,
            onePerRow: true,
        );

        if ($this->returnType !== null) {
            $out .= ': ' . $this->returnType->render($depth);
        }

        if ($this->body === null) {
            return $out . ';';
        }

        // signature-line trailing comment (line-targeted directives must stay put)
        if ($this->headerComment !== null) {
            $out .= ' ' . $this->headerComment->text;
        }

        return $out . "\n" . Layout::indent($depth) . $this->body->render($depth);
    }

    public function firstToken(): SigToken
    {
        return $this->modifiers[0] ?? $this->keyword;
    }

}
