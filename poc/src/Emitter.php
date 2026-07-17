<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use function strlen;
use function substr;

/**
 * Token writer (notes/50): the ONLY way templates produce output. Whitespace exists
 * exclusively as emitter joints, so indentation and blank-line policy live here, in
 * one place — templates physically cannot emit a raw newline.
 *
 * Trailing-trivia invariant (notes/50 §2): a same-line comment attached to a token
 * (SigToken::$trailingComment, set by the Lexer) becomes PENDING when that token is
 * emitted. The next emitter operation must be a line break — which flushes
 * ` <comment>` before the newline — otherwise the comment has no legal position and
 * the render is fatal. One runtime invariant replaces all per-construct guards, and
 * line-targeted directives (`// @phpstan-ignore ...`) can never change lines.
 */
final class Emitter
{

    private string $out = '';

    private ?SigToken $pendingTrailing = null;

    /** @var list<Violation> */
    private array $violations = [];

    public function __construct(
        public readonly string $source,
    )
    {
    }

    public function token(SigToken $token): void
    {
        $this->guard($token->line);
        $this->out .= $token->text;

        if ($token->trailingComment !== null) {
            $this->pendingTrailing = $token->trailingComment;
        }
    }

    public function text(string $text): void
    {
        $this->guard(0);
        $this->out .= $text;
    }

    public function space(): void
    {
        $this->text(' ');
    }

    /** Byte-exact passthrough (verbatim statements/spans, reindented comments). */
    public function verbatim(string $text): void
    {
        $this->guard(0);
        $this->out .= $text;
    }

    public function newline(int $indent, bool $blank = false): void
    {
        $this->flushTrailing();
        $this->out .= "\n" . ($blank ? "\n" : '') . Layout::indent($indent);
    }

    /**
     * THE blank-line policy (single implementation): break onto a new line,
     * preserving 0-1 blank lines as observed before $upcoming (2+ clamped to 1).
     */
    public function lineBreak(SigToken $upcoming, int $indent, bool $allowBlank = true): void
    {
        $this->newline($indent, $allowBlank && $upcoming->newlinesBefore() >= 2);
    }

    /**
     * Register a token's trailing trivia without emitting the token itself —
     * for nodes that emit a transformed text (re-indented comments) via verbatim().
     */
    public function carryTrivia(SigToken $token): void
    {
        if ($token->trailingComment !== null) {
            $this->guard($token->line);
            $this->pendingTrailing = $token->trailingComment;
        }
    }

    public function flushTrailing(): void
    {
        if ($this->pendingTrailing !== null) {
            $this->out .= ' ' . $this->pendingTrailing->text;
            $this->pendingTrailing = null;
        }
    }

    private function guard(int $line): void
    {
        if ($this->pendingTrailing !== null) {
            throw new FatalError('comment must be followed by a line break here', $this->pendingTrailing->line);
        }
    }

    public function offset(): int
    {
        return strlen($this->out);
    }

    public function slice(int $from): string
    {
        return substr($this->out, $from);
    }

    public function violation(Violation $violation): void
    {
        $this->violations[] = $violation;
    }

    public function hasViolationSince(int $outOffset): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->outOffset >= $outOffset) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function result(): string
    {
        $this->guard(0); // a still-pending comment here is a template bug

        return $this->out;
    }

}
