<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Whitespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use const T_BOOLEAN_AND;
use const T_BOOLEAN_OR;
use const T_LOGICAL_AND;
use const T_LOGICAL_OR;
use const T_LOGICAL_XOR;
use const T_WHITESPACE;

final class MultilineConditionSpacingSniff implements Sniff
{

    private const WRONG_SPACING = 'WrongSpacing';

    /**
     * @param int $pointer
     */
    public function process(
        File $phpcsFile,
        $pointer
    ): void
    {
        $tokens = $phpcsFile->getTokens();
        $tokenContent = $tokens[$pointer]['content'];
        $previousEffectivePointer = TokenHelper::findPreviousEffective($phpcsFile, $pointer - 1);

        if ($previousEffectivePointer === null) {
            return;
        }

        $previousToken = $tokens[$previousEffectivePointer];
        $token = $tokens[$pointer];
        $nextToken = $tokens[$pointer + 1];

        if ($nextToken['code'] !== T_WHITESPACE || $nextToken['content'] !== "\n" || $previousToken['line'] !== $token['line']) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            "Wrong multiline condition, '{$tokenContent}' must not be at the end of the line.",
            $pointer,
            self::WRONG_SPACING,
        );

        if (!$fix) {
            return;
        }

        $nextContentPointer = $phpcsFile->findNext(T_WHITESPACE, $pointer + 1, null, true);
        if ($nextContentPointer === false) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();

        $phpcsFile->fixer->replaceToken($pointer, '');

        if ($tokens[$pointer - 1]['code'] === T_WHITESPACE && $tokens[$pointer - 1]['content'] === ' ') {
            $phpcsFile->fixer->replaceToken($pointer - 1, '');
        }

        $phpcsFile->fixer->addContentBefore($nextContentPointer, $tokenContent . ' ');

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * @return list<int>
     */
    public function register(): array
    {
        return [
            T_BOOLEAN_OR,
            T_BOOLEAN_AND,
            T_LOGICAL_OR,
            T_LOGICAL_AND,
            T_LOGICAL_XOR,
        ];
    }

}
