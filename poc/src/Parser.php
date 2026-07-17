<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function in_array;
use function strlen;

/**
 * Layout parser: recursive descent over significant tokens producing the layout tree.
 *
 * This is the default-deny totality gate (notes/40 §2): every token of the file must be
 * consumed by some construct; anything unrecognized is a FatalError — never a silent
 * pass-through.
 */
final class Parser
{

    private const BINARY_OP_CHARS = ['+', '-', '*', '/', '.', '%', '=', '<', '>'];

    private const BINARY_OP_IDS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_COALESCE,
        T_IS_EQUAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_EQUAL,
        T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL,
        T_POW,
        T_SPACESHIP,
    ];

    private const ATOM_IDS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DNUMBER,
        T_LNUMBER,
        T_STRING,
        T_VARIABLE,
    ];

    private int $pos = 0;

    /**
     * @param list<SigToken> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    )
    {
    }

    public function parseFile(): FileNode
    {
        $openTag = $this->expect(T_OPEN_TAG, '<?php open tag');
        $stmts = [];

        while (!$this->peek()->is(SigToken::EOF)) {
            if ($this->peek()->is(T_CLOSE_TAG) || $this->peek()->is(T_INLINE_HTML)) {
                throw new FatalError('close tag / inline HTML is not supported', $this->peek()->line);
            }

            $stmts[] = $this->parseStmt();
        }

        return new FileNode($openTag, $stmts);
    }

    private function parseStmt(): Node
    {
        $token = $this->peek();

        if ($token->isComment()) {
            return new CommentStmt($this->next());
        }

        if ($token->is(T_DECLARE)) {
            return $this->parseDeclare();
        }

        $expr = $this->parseExpr();
        $this->expect(';', '";"');

        return new ExprStmt($expr);
    }

    private function parseDeclare(): DeclareStmt
    {
        $keyword = $this->next();
        $this->expect('(', '"("');
        $directive = $this->expect(T_STRING, 'declare directive name');
        $this->expect('=', '"="');
        $value = $this->parseExpr();
        $this->expect(')', '")"');
        $this->expect(';', '";"');

        return new DeclareStmt($keyword, $directive, $value);
    }

    private function parseExpr(): Node
    {
        $operands = [$this->parseTerm()];
        $ops = [];

        while ($this->isBinaryOp($this->peek())) {
            $ops[] = $this->next();
            $operands[] = $this->parseTerm();
        }

        return $ops === [] ? $operands[0] : new BinChain($operands, $ops);
    }

    private function parseTerm(): Node
    {
        $token = $this->peek();

        if ($token->is('-') || $token->is('+') || $token->is('!')) {
            return new UnaryExpr($this->next(), $this->parseTerm());
        }

        if ($token->is('[')) {
            $open = $this->next();
            [$items, $close] = $this->parseList(']', allowArrow: true);

            return new ArrayLit($open, $items, $close);
        }

        if ($token->is('(')) {
            $open = $this->next();
            $expr = $this->parseExpr();
            $this->expect(')', '")"');

            return new ParenExpr($open, $expr);
        }

        if ($token->is(T_STRING) && $this->tokens[$this->pos + 1]->is('(')) {
            $name = $this->next();
            $open = $this->next();
            [$items, $close] = $this->parseList(')', allowArrow: false);

            return new CallExpr($name, $open, $items, $close);
        }

        if (in_array($token->id, self::ATOM_IDS, true)) {
            return new Atom($this->next());
        }

        throw new FatalError('unsupported syntax "' . $token->text . '"', $token->line);
    }

    /**
     * @return array{list<ArrayItem|CommentRow>, SigToken}
     */
    private function parseList(string $closeChar, bool $allowArrow): array
    {
        $items = [];

        while (!$this->peek()->is($closeChar)) {
            if ($this->peek()->isComment()) {
                $items[] = new CommentRow($this->next());
                continue;
            }

            $expr = $this->parseExpr();
            $key = null;

            if ($allowArrow && $this->peek()->is(T_DOUBLE_ARROW)) {
                $this->next();
                $key = $expr;
                $expr = $this->parseExpr();
            }

            $items[] = new ArrayItem($key, $expr);

            if ($this->peek()->is(',')) {
                $this->next();
                continue;
            }

            if (!$this->peek()->is($closeChar)) {
                if ($this->peek()->isComment()) {
                    throw new FatalError('comment is not allowed here', $this->peek()->line);
                }

                throw new FatalError('expected "," or "' . $closeChar . '", found "' . $this->peek()->text . '"', $this->peek()->line);
            }
        }

        return [$items, $this->next()];
    }

    private function isBinaryOp(SigToken $token): bool
    {
        return in_array($token->id, self::BINARY_OP_IDS, true)
            || (strlen($token->text) === 1 && in_array($token->text, self::BINARY_OP_CHARS, true));
    }

    private function peek(): SigToken
    {
        return $this->tokens[$this->pos];
    }

    private function next(): SigToken
    {
        return $this->tokens[$this->pos++];
    }

    private function expect(int|string $kind, string $description): SigToken
    {
        $token = $this->peek();

        if (!$token->is($kind)) {
            $found = $token->text === '' ? 'end of file' : $token->text;

            throw new FatalError('expected ' . $description . ', found "' . $found . '"', $token->line);
        }

        return $this->next();
    }

}
