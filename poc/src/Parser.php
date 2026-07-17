<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function count;
use function in_array;
use function preg_match;
use function strlen;

/**
 * Layout parser: recursive descent over significant tokens producing the layout tree.
 *
 * This is the default-deny totality gate (notes/40 §2): every token of the file must
 * be consumed by some construct. Unrecognized constructs degrade to STATEMENT-level
 * verbatim passthrough + Violation (error recovery, notes/50 §4) — never a silent
 * pass-through, and only file-level problems (invalid PHP, inline HTML) are fatal.
 *
 * The parser only RECOGNIZES; enforcement lives in the templates/Emitter. Same-line
 * comments never appear here — the Lexer turned them into trailing trivia.
 */
final class Parser
{

    private const BINARY_OP_CHARS = ['+', '-', '*', '/', '.', '%', '=', '<', '>', '|', '^', '&'];

    private const BINARY_OP_IDS = [
        T_AND_EQUAL,
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_COALESCE,
        T_COALESCE_EQUAL,
        T_CONCAT_EQUAL,
        T_DIV_EQUAL,
        T_INSTANCEOF,
        T_IS_EQUAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_EQUAL,
        T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
        T_MINUS_EQUAL,
        T_MOD_EQUAL,
        T_MUL_EQUAL,
        T_OR_EQUAL,
        T_PLUS_EQUAL,
        T_POW,
        T_POW_EQUAL,
        T_SL,
        T_SL_EQUAL,
        T_SPACESHIP,
        T_SR,
        T_SR_EQUAL,
        T_XOR_EQUAL,
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    private const ATOM_IDS = [
        T_CLASS_C,
        T_CONSTANT_ENCAPSED_STRING,
        T_DIR,
        T_DNUMBER,
        T_FILE,
        T_FUNC_C,
        T_LINE,
        T_LNUMBER,
        T_METHOD_C,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_NS_C,
        T_STRING,
        T_VARIABLE,
    ];

    private const CAST_IDS = [
        T_ARRAY_CAST,
        T_BOOL_CAST,
        T_DOUBLE_CAST,
        T_INT_CAST,
        T_OBJECT_CAST,
        T_STRING_CAST,
        T_UNSET_CAST,
    ];

    private const MODIFIER_IDS = [
        T_ABSTRACT,
        T_FINAL,
        T_PRIVATE,
        T_PROTECTED,
        T_PUBLIC,
        T_READONLY,
        T_STATIC,
        T_VAR,
    ];

    private const CLASS_KEYWORD_IDS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const TYPE_NAME_IDS = [
        T_ARRAY,
        T_CALLABLE,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_STATIC,
        T_STRING,
    ];

    private const KEYWORD_STMT_IDS = [
        T_BREAK,
        T_CONTINUE,
        T_ECHO,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_RETURN,
        T_THROW,
    ];

    private const NAME_IDS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    /** @var list<Violation> */
    public array $violations = [];

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

            $stmts[] = $this->parseStmtRecovering();
        }

        return new FileNode($openTag, $stmts, $this->peek());
    }

    // ---------------------------------------------------------------- recovery

    private function parseStmtRecovering(): Node
    {
        $start = $this->pos;

        try {
            return $this->parseStmt();
        } catch (FatalError $error) {
            return $this->recoverVerbatim($start, $error);
        }
    }

    private function parseMemberRecovering(bool $inEnum): Node
    {
        $start = $this->pos;

        try {
            return $this->parseMember($inEnum);
        } catch (FatalError $error) {
            return $this->recoverVerbatim($start, $error);
        }
    }

    /**
     * Statement-level error recovery (notes/50 §4): rewind to the statement start,
     * scan with bracket counting to its terminator (`;` at depth 0 or a balanced
     * `}` body), freeze the span verbatim, report a Violation.
     */
    private function recoverVerbatim(int $start, FatalError $error): VerbatimStmt
    {
        $this->pos = $start;
        $tokens = [];
        $depth = 0;
        $sawBrace = false;

        while (true) {
            $token = $this->peek();

            if ($token->is(SigToken::EOF)) {
                break;
            }

            if ($depth === 0 && $tokens !== [] && $token->is('}')) {
                break; // the enclosing block's close — leave it for the caller
            }

            $this->next();
            $tokens[] = $token;

            if ($token->is('(') || $token->is('[')) {
                $depth++;
            } elseif ($token->is('{')) {
                $depth++;
                $sawBrace = true;
            } elseif ($token->is(')') || $token->is(']')) {
                $depth--;
            } elseif ($token->is('}')) {
                $depth--;

                if ($depth <= 0 && $sawBrace) {
                    break;
                }

                if ($depth < 0) {
                    // stray closer belongs to the caller — put it back
                    $this->pos--;
                    array_pop($tokens);
                    break;
                }
            } elseif ($token->is(';') && $depth === 0) {
                break;
            }
        }

        if ($tokens === []) {
            throw $error;
        }

        $this->violations[] = new Violation(
            $tokens[0]->line,
            'unsupported construct kept as-is: ' . $error->getMessage(),
        );

        return new VerbatimStmt($tokens);
    }

    // ---------------------------------------------------------------- statements

    private function parseStmt(): Node
    {
        $token = $this->peek();

        if ($token->isComment()) {
            return new CommentStmt($this->next());
        }

        if ($token->is(T_ATTRIBUTE)) {
            return new AttributedNode($this->parseAttrGroups(), $this->parseStmt());
        }

        if (($token->is(T_STATIC) || $token->is(T_GLOBAL)) && $this->peekAt(1)->is(T_VARIABLE)) {
            return $this->parseVarList();
        }

        if ($token->is(T_DECLARE)) {
            return $this->parseDeclare();
        }

        if ($token->is(T_NAMESPACE)) {
            $kw = $this->next();
            $name = $this->expectAny(self::NAME_IDS, 'namespace name');

            return new NamespaceStmt($kw, $name, $this->expect(';', '";"'));
        }

        if ($token->is(T_USE)) {
            return $this->parseUse();
        }

        if (in_array($token->id, self::KEYWORD_STMT_IDS, true)) {
            $kw = $this->next();
            $expr = $this->peek()->is(';') ? null : $this->parseExpr();

            return new SimpleStmt($kw, $expr, $this->expect(';', '";"'));
        }

        if ($token->is(T_IF)) {
            return $this->parseIf();
        }

        if ($token->is(T_WHILE)) {
            $kw = $this->next();
            $cond = $this->parseCond();

            return new WhileStmt($kw, $cond, $this->parseBlock());
        }

        if ($token->is(T_DO)) {
            $kw = $this->next();
            $block = $this->parseBlock();
            $this->expect(T_WHILE, '"while"');
            $cond = $this->parseCond();

            return new DoWhileStmt($kw, $block, $cond, $this->expect(';', '";"'));
        }

        if ($token->is(T_FOR)) {
            return $this->parseFor();
        }

        if ($token->is(T_FOREACH)) {
            return $this->parseForeach();
        }

        if ($token->is(T_SWITCH)) {
            return $this->parseSwitch();
        }

        if ($token->is(T_TRY)) {
            return $this->parseTry();
        }

        if ($token->is(T_FUNCTION) && $this->peekAt(1)->is(T_STRING)) {
            return $this->parseFunctionDecl([]);
        }

        if (in_array($token->id, self::CLASS_KEYWORD_IDS, true) || $this->looksLikeClassDecl()) {
            return $this->parseClassLike();
        }

        $expr = $this->parseExpr();

        return new ExprStmt($expr, $this->expect(';', '";"'));
    }

    private function looksLikeClassDecl(): bool
    {
        $i = 0;

        while (in_array($this->peekAt($i)->id, self::MODIFIER_IDS, true)) {
            $i++;
        }

        return $i > 0 && in_array($this->peekAt($i)->id, self::CLASS_KEYWORD_IDS, true);
    }

    private function parseDeclare(): DeclareStmt
    {
        $keyword = $this->next();
        $this->expect('(', '"("');
        $directive = $this->expect(T_STRING, 'declare directive name');
        $this->expect('=', '"="');
        $value = $this->parseExpr();
        $this->expect(')', '")"');

        return new DeclareStmt($keyword, $directive, $value, $this->expect(';', '";"'));
    }

    private function parseUse(): UseStmt
    {
        $kw = $this->next();
        $kind = null;

        if ($this->peek()->is(T_FUNCTION) || $this->peek()->is(T_CONST)) {
            $kind = $this->next();
        }

        $name = $this->expectAny(self::NAME_IDS, 'import name');
        $alias = null;

        if ($this->peek()->is(T_AS)) {
            $this->next();
            $alias = $this->expect(T_STRING, 'alias name');
        }

        return new UseStmt($kw, $kind, $name, $alias, $this->expect(';', '";"'));
    }

    private function parseVarList(): VarListStmt
    {
        $kw = $this->next();
        $vars = [];

        do {
            $var = $this->expect(T_VARIABLE, 'variable');
            $default = null;

            if ($this->peek()->is('=')) {
                $this->next();
                $default = $this->parseExpr();
            }

            $vars[] = [$var, $default];

            if (!$this->peek()->is(',')) {
                break;
            }

            $this->next();
        } while (true);

        return new VarListStmt($kw, $vars, $this->expect(';', '";"'));
    }

    private function parseCond(): Cond
    {
        $open = $this->expect('(', '"("');
        $expr = $this->parseExpr();

        return new Cond($open, $expr, $this->expect(')', '")"'));
    }

    private function parseIf(): IfStmt
    {
        $kw = $this->next();
        $cond = $this->parseCond();
        $then = $this->parseBlock();
        $elseifs = [];
        $else = null;

        while ($this->peek()->is(T_ELSEIF)) {
            $eKw = $this->next();
            $elseifs[] = [$eKw, $this->parseCond(), $this->parseBlock()];
        }

        if ($this->peek()->is(T_ELSE)) {
            $this->next();

            if ($this->peek()->is(T_IF)) {
                throw new FatalError('"else if" is not supported, use "elseif"', $this->peek()->line);
            }

            $else = $this->parseBlock();
        }

        return new IfStmt($kw, $cond, $then, $elseifs, $else);
    }

    private function parseFor(): ForStmt
    {
        $kw = $this->next();
        $this->expect('(', '"("');
        $init = $this->parseExprList(';');
        $this->expect(';', '";"');
        $cond = $this->peek()->is(';') ? null : $this->parseExpr();
        $this->expect(';', '";"');
        $step = $this->parseExprList(')');
        $this->expect(')', '")"');

        return new ForStmt($kw, $init, $cond, $step, $this->parseBlock());
    }

    /**
     * @return list<Node>
     */
    private function parseExprList(string $stopChar): array
    {
        if ($this->peek()->is($stopChar)) {
            return [];
        }

        $exprs = [$this->parseExpr()];

        while ($this->peek()->is(',')) {
            $this->next();
            $exprs[] = $this->parseExpr();
        }

        return $exprs;
    }

    private function parseForeach(): ForeachStmt
    {
        $kw = $this->next();
        $this->expect('(', '"("');
        $subject = $this->parseExpr();
        $this->expect(T_AS, '"as"');
        $byRef = null;

        if ($this->isAmp($this->peek())) {
            $byRef = $this->next();
        }

        $first = $this->parseExpr();
        $key = null;
        $value = $first;

        if ($this->peek()->is(T_DOUBLE_ARROW)) {
            $this->next();
            $key = $first;

            if ($this->isAmp($this->peek())) {
                $byRef = $this->next();
            }

            $value = $this->parseExpr();
        }

        $this->expect(')', '")"');

        return new ForeachStmt($kw, $subject, $key, $byRef, $value, $this->parseBlock());
    }

    private function parseSwitch(): SwitchStmt
    {
        $kw = $this->next();
        $subject = $this->parseCond();
        $braceOpen = $this->expect('{', '"{"');
        $cases = [];

        while (!$this->peek()->is('}')) {
            $caseKw = $this->peek();

            if ($caseKw->is(T_CASE)) {
                $this->next();
                $expr = $this->parseExpr();
            } elseif ($caseKw->is(T_DEFAULT)) {
                $this->next();
                $expr = null;
            } else {
                throw new FatalError('expected "case", "default" or "}" in switch, found "' . $caseKw->text . '"', $caseKw->line);
            }

            $colon = $this->expect(':', '":"');
            $stmts = [];

            while (!$this->peek()->is('}') && !$this->peek()->is(T_CASE) && !$this->peek()->is(T_DEFAULT)) {
                $stmts[] = $this->parseStmtRecovering();
            }

            $cases[] = new SwitchCase($caseKw, $expr, $colon, $stmts);
        }

        return new SwitchStmt($kw, $subject, $braceOpen, $cases, $this->next());
    }

    private function parseTry(): TryStmt
    {
        $kw = $this->next();
        $block = $this->parseBlock();
        $catches = [];
        $finally = null;

        while ($this->peek()->is(T_CATCH)) {
            $cKw = $this->next();
            $this->expect('(', '"("');
            $types = [$this->expectAny(self::NAME_IDS, 'exception type')];

            while ($this->peek()->is('|')) {
                $this->next();
                $types[] = $this->expectAny(self::NAME_IDS, 'exception type');
            }

            $var = $this->peek()->is(T_VARIABLE) ? $this->next() : null;
            $this->expect(')', '")"');
            $catches[] = new CatchClause($cKw, $types, $var, $this->parseBlock());
        }

        if ($this->peek()->is(T_FINALLY)) {
            $this->next();
            $finally = $this->parseBlock();
        }

        return new TryStmt($kw, $block, $catches, $finally);
    }

    private function parseBlock(): Block
    {
        $open = $this->expect('{', '"{"');
        $stmts = [];

        while (!$this->peek()->is('}')) {
            $stmts[] = $this->parseStmtRecovering();
        }

        return new Block($open, $stmts, $this->next());
    }

    // ---------------------------------------------------------------- class-likes

    private function parseClassLike(): ClassDecl
    {
        $modifiers = $this->takeModifiers();
        $keyword = $this->next();
        $name = $this->expect(T_STRING, 'class name');
        $enumBacking = null;

        if ($keyword->is(T_ENUM) && $this->peek()->is(':')) {
            $this->next();
            $enumBacking = $this->parseType();
        }

        $extends = [];
        $implements = [];

        if ($this->peek()->is(T_EXTENDS)) {
            $this->next();
            $extends = $this->parseNameList();
        }

        if ($this->peek()->is(T_IMPLEMENTS)) {
            $this->next();
            $implements = $this->parseNameList();
        }

        $bodyOpen = $this->expect('{', '"{"');
        $members = [];

        while (!$this->peek()->is('}')) {
            $members[] = $this->parseMemberRecovering($keyword->is(T_ENUM));
        }

        return new ClassDecl($modifiers, $keyword, $name, $enumBacking, $extends, $implements, $bodyOpen, $members, $this->next());
    }

    /**
     * @return list<SigToken>
     */
    private function parseNameList(): array
    {
        $names = [$this->expectAny(self::NAME_IDS, 'name')];

        while ($this->peek()->is(',')) {
            $this->next();
            $names[] = $this->expectAny(self::NAME_IDS, 'name');
        }

        return $names;
    }

    private function parseMember(bool $inEnum): Node
    {
        $token = $this->peek();

        if ($token->isComment()) {
            return new CommentStmt($this->next());
        }

        if ($token->is(T_ATTRIBUTE)) {
            return new AttributedNode($this->parseAttrGroups(), $this->parseMember($inEnum));
        }

        if ($token->is(T_USE)) {
            return $this->parseUse();
        }

        if ($inEnum && $token->is(T_CASE)) {
            $kw = $this->next();
            $name = $this->expect(T_STRING, 'enum case name');
            $value = null;

            if ($this->peek()->is('=')) {
                $this->next();
                $value = $this->parseExpr();
            }

            return new EnumCase($kw, $name, $value, $this->expect(';', '";"'));
        }

        $modifiers = $this->takeModifiers();

        if ($this->peek()->is(T_CONST)) {
            $kw = $this->next();
            $name = $this->expect(T_STRING, 'constant name');
            $this->expect('=', '"="');
            $value = $this->parseExpr();

            return new ConstMember($modifiers, $kw, $name, $value, $this->expect(';', '";"'));
        }

        if ($this->peek()->is(T_FUNCTION)) {
            return $this->parseFunctionDecl($modifiers);
        }

        $type = $this->peek()->is(T_VARIABLE) ? null : $this->parseType();
        $var = $this->expect(T_VARIABLE, 'property name');
        $default = null;

        if ($this->peek()->is('=')) {
            $this->next();
            $default = $this->parseExpr();
        }

        return new PropertyMember($modifiers, $type, $var, $default, $this->expect(';', '";"'));
    }

    /**
     * @param list<SigToken> $modifiers
     */
    private function parseFunctionDecl(array $modifiers): FunctionDecl
    {
        $kw = $this->next();
        $name = $this->expectMemberName(); // method names may be (semi-)reserved keywords
        [$open, $params, $close] = $this->parseParams();
        $returnType = null;

        if ($this->peek()->is(':')) {
            $this->next();
            $returnType = $this->parseType();
        }

        $body = null;
        $semi = null;

        if ($this->peek()->is('{')) {
            $body = $this->parseBlock();
        } else {
            $semi = $this->expect(';', '";" or "{"');
        }

        return new FunctionDecl($modifiers, $kw, $name, $open, $params, $close, $returnType, $body, $semi);
    }

    /**
     * @return array{SigToken, list<ListItem|CommentRow>, SigToken}
     */
    private function parseParams(): array
    {
        $open = $this->expect('(', '"("');
        $items = [];

        while (!$this->peek()->is(')')) {
            if ($this->peek()->isComment()) {
                $items[] = new CommentRow($this->next());
                continue;
            }

            $attrGroups = $this->peek()->is(T_ATTRIBUTE) ? $this->parseAttrGroups() : [];
            $modifiers = $this->takeModifiers();
            $type = null;

            if (!$this->peek()->is(T_VARIABLE) && !$this->isAmp($this->peek()) && !$this->peek()->is(T_ELLIPSIS)) {
                $type = $this->parseType();
            }

            $byRef = $this->isAmp($this->peek()) ? $this->next() : null;
            $variadic = $this->peek()->is(T_ELLIPSIS) ? $this->next() : null;
            $var = $this->expect(T_VARIABLE, 'parameter name');
            $default = null;

            if ($this->peek()->is('=')) {
                $this->next();
                $default = $this->parseExpr();
            }

            $comma = $this->takeListSeparator(')');
            $items[] = new Param($attrGroups, $modifiers, $type, $byRef, $variadic, $var, $default, $comma);
        }

        return [$open, $items, $this->next()];
    }

    private function takeListSeparator(string $closeChar): ?SigToken
    {
        if ($this->peek()->is(',')) {
            return $this->next();
        }

        if (!$this->peek()->is($closeChar)) {
            throw new FatalError('expected "," or "' . $closeChar . '", found "' . $this->peek()->text . '"', $this->peek()->line);
        }

        return null;
    }

    private function parseType(): TypeNode
    {
        $tokens = [];
        $parens = 0;

        if ($this->peek()->is('?')) {
            $tokens[] = $this->next();
        }

        while (true) {
            $token = $this->peek();

            if (in_array($token->id, self::TYPE_NAME_IDS, true) || $token->is('|') || $this->isAmp($token)) {
                $tokens[] = $this->next();
                continue;
            }

            if ($token->is('(')) {
                $parens++;
                $tokens[] = $this->next();
                continue;
            }

            if ($token->is(')') && $parens > 0) {
                $parens--;
                $tokens[] = $this->next();
                continue;
            }

            break;
        }

        if ($tokens === []) {
            throw new FatalError('expected type, found "' . $this->peek()->text . '"', $this->peek()->line);
        }

        return new TypeNode($tokens);
    }

    /**
     * @return list<SigToken>
     */
    private function takeModifiers(): array
    {
        $modifiers = [];

        while (in_array($this->peek()->id, self::MODIFIER_IDS, true)) {
            $modifiers[] = $this->next();
        }

        return $modifiers;
    }

    /**
     * @return non-empty-list<AttrGroup>
     */
    private function parseAttrGroups(): array
    {
        $groups = [];

        while ($this->peek()->is(T_ATTRIBUTE)) {
            $open = $this->next();
            $attrs = [];

            while (true) {
                $name = $this->expectAny(self::NAME_IDS, 'attribute name');
                $argsOpen = null;
                $args = [];
                $argsClose = null;

                if ($this->peek()->is('(')) {
                    $argsOpen = $this->next();
                    [$args, $argsClose] = $this->parseList(')', allowArrow: false, allowNamed: true);
                }

                $attrs[] = [$name, $argsOpen, $args, $argsClose];

                if ($this->peek()->is(',')) {
                    $this->next();
                    continue;
                }

                break;
            }

            $this->expect(']', '"]"');
            $groups[] = new AttrGroup($open, $attrs);
        }

        return $groups;
    }

    // ---------------------------------------------------------------- expressions

    private function parseExpr(): Node
    {
        $operands = [$this->parseTerm()];
        $ops = [];

        while ($this->isBinaryOp($this->peek())) {
            $ops[] = $this->next();
            $operands[] = $this->parseTerm();
        }

        $node = $ops === [] ? $operands[0] : new BinChain($operands, $ops);

        if ($this->peek()->is('?')) {
            $question = $this->next();

            if ($this->peek()->is(':')) {
                $colon = $this->next();

                return new Ternary($node, $question, null, $colon, $this->parseExpr());
            }

            $then = $this->parseExpr();
            $colon = $this->expect(':', '":"');

            return new Ternary($node, $question, $then, $colon, $this->parseExpr());
        }

        return $node;
    }

    private function parseTerm(): Node
    {
        $token = $this->peek();

        if ($token->is('-') || $token->is('+') || $token->is('!') || $token->is('@') || $token->is('~')
            || $token->is(T_INC) || $token->is(T_DEC) || $token->is(T_ELLIPSIS) || $this->isAmp($token)
        ) {
            return new UnaryExpr($this->next(), $this->parseTerm());
        }

        if (in_array($token->id, self::CAST_IDS, true)) {
            return new CastExpr($this->next(), $this->parseTerm());
        }

        return $this->parsePostfix($this->parsePrimary());
    }

    private function parsePrimary(): Node
    {
        $token = $this->peek();

        if ($token->is(T_NEW)) {
            return $this->parseNew();
        }

        if ($token->is(T_CLONE) || $token->is(T_PRINT) || $token->is(T_YIELD_FROM) || $token->is(T_THROW)
            || $token->is(T_INCLUDE) || $token->is(T_INCLUDE_ONCE) || $token->is(T_REQUIRE) || $token->is(T_REQUIRE_ONCE)
        ) {
            return new KeywordExpr($this->next(), $this->parseExpr());
        }

        if ($token->is(T_YIELD)) {
            $kw = $this->next();

            foreach ([';', ')', ']', ','] as $stop) {
                if ($this->peek()->is($stop)) {
                    return new KeywordExpr($kw, null);
                }
            }

            $expr = $this->parseExpr();

            if ($this->peek()->is(T_DOUBLE_ARROW)) {
                $arrow = $this->next();
                $expr = new ArrayItem($expr, $arrow, $this->parseExpr(), null);
            }

            return new KeywordExpr($kw, $expr);
        }

        if ($token->is(T_MATCH)) {
            return $this->parseMatch();
        }

        if ($token->is(T_STATIC) || $token->is(T_FUNCTION) || $token->is(T_FN)) {
            $static = null;

            if ($token->is(T_STATIC)) {
                if (!$this->peekAt(1)->is(T_FUNCTION) && !$this->peekAt(1)->is(T_FN)) {
                    return new Atom($this->next()); // static::... reference
                }

                $static = $this->next();
            }

            return $this->peek()->is(T_FN) ? $this->parseArrowFn($static) : $this->parseClosure($static);
        }

        if ($token->is(T_START_HEREDOC)) {
            return $this->parseVerbatimUntil(T_END_HEREDOC);
        }

        if ($token->is('"')) {
            return $this->parseVerbatimUntil('"');
        }

        if ($token->is('[')) {
            $open = $this->next();
            [$items, $close] = $this->parseList(']', allowArrow: true);

            return new ArrayLit($open, $items, $close);
        }

        if ($token->is('(')) {
            $open = $this->next();
            $expr = $this->parseExpr();

            return new ParenExpr($open, $expr, $this->expect(')', '")"'));
        }

        if ($token->is(T_ISSET) || $token->is(T_EMPTY) || $token->is(T_UNSET) || $token->is(T_EXIT) || $token->is(T_LIST)) {
            return new Atom($this->next()); // call parens handled as postfix invoke
        }

        if (in_array($token->id, self::ATOM_IDS, true)) {
            return new Atom($this->next());
        }

        throw new FatalError('unsupported syntax "' . $token->text . '"', $token->line);
    }

    private function parsePostfix(Node $node): Node
    {
        $segments = [];

        while (true) {
            $token = $this->peek();

            if ($token->is(T_OBJECT_OPERATOR) || $token->is(T_NULLSAFE_OBJECT_OPERATOR)) {
                $op = $this->next();
                $name = $this->expectMemberName();

                if ($this->peek()->is('(')) {
                    $open = $this->next();
                    [$items, $close] = $this->parseList(')', allowArrow: false, allowNamed: true);
                    $segments[] = new Segment(Segment::CALL, op: $op, name: $name, open: $open, items: $items, close: $close);
                } else {
                    $segments[] = new Segment(Segment::PROP, op: $op, name: $name);
                }

                continue;
            }

            if ($token->is(T_DOUBLE_COLON)) {
                $op = $this->next();
                $name = $this->expectMemberName();

                if ($this->peek()->is('(')) {
                    $open = $this->next();
                    [$items, $close] = $this->parseList(')', allowArrow: false, allowNamed: true);
                    $segments[] = new Segment(Segment::STATIC_CALL, op: $op, name: $name, open: $open, items: $items, close: $close);
                } else {
                    $segments[] = new Segment(Segment::STATIC_REF, op: $op, name: $name);
                }

                continue;
            }

            if ($token->is('[')) {
                $open = $this->next();
                $index = $this->peek()->is(']') ? null : $this->parseExpr();
                $segments[] = new Segment(Segment::INDEX, open: $open, index: $index, close: $this->expect(']', '"]"'));
                continue;
            }

            if ($token->is('(')) {
                $open = $this->next();
                [$items, $close] = $this->parseList(')', allowArrow: false, allowNamed: true);
                $segments[] = new Segment(Segment::INVOKE, open: $open, items: $items, close: $close);
                continue;
            }

            if ($token->is(T_INC) || $token->is(T_DEC)) {
                $segments[] = new Segment(Segment::INC_DEC, op: $this->next());
                continue;
            }

            break;
        }

        return $segments === [] ? $node : new AccessChain($node, $segments);
    }

    private function parseNew(): NewExpr
    {
        $kw = $this->next();
        $class = new Atom($this->expectAny(
            [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_VARIABLE, T_STATIC],
            'class name',
        ));
        $open = null;
        $items = [];
        $close = null;

        if ($this->peek()->is('(')) {
            $open = $this->next();
            [$items, $close] = $this->parseList(')', allowArrow: false, allowNamed: true);
        }

        return new NewExpr($kw, $class, $open, $items, $close);
    }

    private function parseMatch(): MatchExpr
    {
        $kw = $this->next();
        $subject = $this->parseCond();
        $braceOpen = $this->expect('{', '"{"');
        $arms = [];

        while (!$this->peek()->is('}')) {
            if ($this->peek()->isComment()) {
                $arms[] = new CommentRow($this->next());
                continue;
            }

            $default = null;
            $conds = [];

            if ($this->peek()->is(T_DEFAULT)) {
                $default = $this->next();
            } else {
                $conds[] = $this->parseExpr();

                while ($this->peek()->is(',') && !$this->peekAt(1)->is(T_DOUBLE_ARROW)) {
                    $this->next();
                    $conds[] = $this->parseExpr();
                }

                if ($this->peek()->is(',')) {
                    $this->next(); // trailing comma before => (layout comma)
                }
            }

            $arrow = $this->expect(T_DOUBLE_ARROW, '"=>"');
            $body = $this->parseExpr();
            $comma = $this->peek()->is(',') ? $this->next() : null;

            $arms[] = new MatchArm($default, $conds, $arrow, $body, $comma);
        }

        return new MatchExpr($kw, $subject, $braceOpen, $arms, $this->next());
    }

    private function parseClosure(?SigToken $static): ClosureExpr
    {
        $kw = $this->next();
        [$open, $params, $close] = $this->parseParams();
        $usesOpen = null;
        $uses = [];
        $usesClose = null;

        if ($this->peek()->is(T_USE)) {
            $this->next();
            $usesOpen = $this->expect('(', '"("');
            [$uses, $usesClose] = $this->parseList(')', allowArrow: false);
        }

        $returnType = null;

        if ($this->peek()->is(':')) {
            $this->next();
            $returnType = $this->parseType();
        }

        return new ClosureExpr($static, $kw, $open, $params, $close, $usesOpen, $uses, $usesClose, $returnType, $this->parseBlock());
    }

    private function parseArrowFn(?SigToken $static): ArrowFnExpr
    {
        $kw = $this->next();
        [$open, $params, $close] = $this->parseParams();
        $returnType = null;

        if ($this->peek()->is(':')) {
            $this->next();
            $returnType = $this->parseType();
        }

        $arrow = $this->expect(T_DOUBLE_ARROW, '"=>"');

        return new ArrowFnExpr($static, $kw, $open, $params, $close, $returnType, $arrow, $this->parseExpr());
    }

    private function parseVerbatimUntil(int|string $endKind): VerbatimSpan
    {
        $tokens = [$this->next()];

        while (!$this->peek()->is($endKind)) {
            if ($this->peek()->is(SigToken::EOF)) {
                throw new FatalError('unterminated string', $tokens[0]->line);
            }

            $tokens[] = $this->next();
        }

        $tokens[] = $this->next();

        return new VerbatimSpan($tokens);
    }

    /**
     * @return array{list<ListItem|CommentRow>, SigToken}
     */
    private function parseList(string $closeChar, bool $allowArrow, bool $allowNamed = false): array
    {
        $items = [];

        while (!$this->peek()->is($closeChar)) {
            if ($this->peek()->isComment()) {
                $items[] = new CommentRow($this->next());
                continue;
            }

            // first-class callable syntax: foo(...)
            if ($this->peek()->is(T_ELLIPSIS) && $this->peekAt(1)->is($closeChar)) {
                $items[] = new ArrayItem(null, null, new Atom($this->next()), null);
                continue;
            }

            $key = null;
            $arrow = null;
            $named = false;

            if ($allowNamed && $this->peek()->is(T_STRING) && $this->peekAt(1)->is(':')) {
                $key = new Atom($this->next());
                $arrow = $this->next(); // the `:`
                $named = true;
                $value = $this->parseExpr();
            } else {
                $value = $this->parseExpr();

                if ($allowArrow && $this->peek()->is(T_DOUBLE_ARROW)) {
                    $arrow = $this->next();
                    $key = $value;
                    $value = $this->parseExpr();
                }
            }

            $comma = $this->takeListSeparator($closeChar);
            $items[] = new ArrayItem($key, $arrow, $value, $comma, $named);
        }

        return [$items, $this->next()];
    }

    // ---------------------------------------------------------------- helpers

    private function isBinaryOp(SigToken $token): bool
    {
        return in_array($token->id, self::BINARY_OP_IDS, true)
            || (strlen($token->text) === 1 && in_array($token->text, self::BINARY_OP_CHARS, true));
    }

    private function isAmp(SigToken $token): bool
    {
        return $token->text === '&';
    }

    private function peek(): SigToken
    {
        return $this->tokens[$this->pos];
    }

    private function peekAt(int $offset): SigToken
    {
        return $this->tokens[$this->pos + $offset] ?? $this->tokens[count($this->tokens) - 1];
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

    /**
     * Member names after `->` / `::` may be any identifier-shaped token — PHP allows
     * (semi-)reserved keywords there (`Config::DEFAULT`, `$x->list`).
     */
    private function expectMemberName(): SigToken
    {
        $token = $this->peek();

        if ($token->is(T_VARIABLE) || preg_match('~^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$~', $token->text) === 1) {
            return $this->next();
        }

        throw new FatalError('expected member name, found "' . $token->text . '"', $token->line);
    }

    /**
     * @param list<int> $ids
     */
    private function expectAny(array $ids, string $description): SigToken
    {
        $token = $this->peek();

        if (!in_array($token->id, $ids, true)) {
            $found = $token->text === '' ? 'end of file' : $token->text;

            throw new FatalError('expected ' . $description . ', found "' . $found . '"', $token->line);
        }

        return $this->next();
    }

}
