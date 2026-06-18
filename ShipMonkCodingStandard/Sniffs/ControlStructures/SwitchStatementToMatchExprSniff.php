<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use const T_SWITCH;

final class SwitchStatementToMatchExprSniff implements Sniff
{

    public const CODE_SWITCH_STATEMENT_TO_MATCH_EXPR = 'SwitchStatementToMatchExpr';

    /**
     * @return list<int>
     */
    public function register(): array
    {
        return [T_SWITCH];
    }

    /**
     * @param int $pointer
     */
    public function process(
        File $phpcsFile,
        $pointer
    ): void
    {
        $phpcsFile->addError('Using switch statement is forbidden, use match expression (PHP 8) instead.', $pointer, self::CODE_SWITCH_STATEMENT_TO_MATCH_EXPR);
    }

}
