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

    public function render(int $depth): string
    {
        return match ($this->kind) {
            self::PROP => $this->op->text . $this->name->text,
            self::CALL => $this->op->text . $this->name->text
                . CollectionLayout::render($this->open, $this->items, $this->close, $depth),
            self::STATIC_REF => '::' . $this->name->text,
            self::STATIC_CALL => '::' . $this->name->text
                . CollectionLayout::render($this->open, $this->items, $this->close, $depth),
            self::INDEX => '[' . ($this->index?->render($depth) ?? '') . ']',
            self::INVOKE => CollectionLayout::render($this->open, $this->items, $this->close, $depth),
            self::INC_DEC => $this->op->text,
        };
    }

    public function isObjectOp(): bool
    {
        return $this->kind === self::PROP || $this->kind === self::CALL;
    }

}
