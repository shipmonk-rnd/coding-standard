<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Whitespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use const T_INLINE_ELSE;
use const T_INLINE_THEN;
use const T_WHITESPACE;

final class MultilineTernarySniff implements Sniff
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
        $nextToken = $tokens[$pointer + 1];

        if ($nextToken['code'] !== T_WHITESPACE || $nextToken['content'] !== "\n") {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            "Wrong multiline ternary condition, '{$tokenContent}' must not be at the end of the line.",
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
     * @return list<string>
     */
    public function register(): array
    {
        return [
            T_INLINE_THEN,
            T_INLINE_ELSE,
        ];
    }

}
