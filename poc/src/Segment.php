<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * One postfix segment of an access chain: `->prop`, `->method(args)`, `::CONST`,
 * `::method(args)`, `[index]`, `(args)` invocation, `++`/`--`.
 */
final class Segment
{

    public const PROP = 'prop';
    public const CALL = 'call';
    public const STATIC_REF = 'static';
    public const STATIC_CALL = 'staticCall';
    public const INDEX = 'index';
    public const INVOKE = 'invoke';
    public const INC_DEC = 'incdec';

    /**
     * @param list<ListItem|CommentRow> $items
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?SigToken $op = null,
        public readonly ?SigToken $name = null,
        public readonly ?SigToken $open = null,
        public readonly array $items = [],
        public readonly ?SigToken $close = null,
        public readonly ?Node $index = null,
    )
    {
    }

    public function render(Emitter $e, RenderCtx $ctx): void
    {
        switch ($this->kind) {
            case self::PROP:
            case self::STATIC_REF:
                $e->token($this->op);
                $e->token($this->name);
                break;
            case self::CALL:
            case self::STATIC_CALL:
                $e->token($this->op);
                $e->token($this->name);
                CollectionLayout::render($e, $this->open, $this->items, $this->close, $ctx);
                break;
            case self::INDEX:
                $e->token($this->open);
                $this->index?->render($e, $ctx);
                $e->token($this->close);
                break;
            case self::INVOKE:
                CollectionLayout::render($e, $this->open, $this->items, $this->close, $ctx);
                break;
            case self::INC_DEC:
                $e->token($this->op);
                break;
        }
    }

    public function isObjectOp(): bool
    {
        return $this->kind === self::PROP || $this->kind === self::CALL;
    }

}
