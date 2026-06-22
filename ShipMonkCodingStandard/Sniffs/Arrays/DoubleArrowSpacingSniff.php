<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use function in_array;
use function ltrim;
use function strpos;
use const T_DOUBLE_ARROW;
use const T_FN_ARROW;
use const T_MATCH_ARROW;
use const T_WHITESPACE;

final class DoubleArrowSpacingSniff implements Sniff
{

    private const WRONG_SPACING_AROUND_DOUBLE_ARROW = 'WrongSpacingAroundDoubleArrow';

    /**
     * @param int $pointer
     */
    public function process(
        File $phpcsFile,
        $pointer
    ): void
    {
        $tokens = $phpcsFile->getTokens();

        $before = $tokens[$pointer - 1];
        $after = $tokens[$pointer + 1];

        $beforeValid = in_array($before['content'], [' ', "\n"], true);
        $afterValid = in_array($after['content'], [' ', "\n"], true);

        if ($beforeValid && $afterValid) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Expected 1 space (or newline) before and after double arrow',
            $pointer,
            self::WRONG_SPACING_AROUND_DOUBLE_ARROW,
        );

        if (!$fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();

        if (!$beforeValid) {
            if ($before['code'] !== T_WHITESPACE) {
                $phpcsFile->fixer->addContentBefore($pointer, ' ');
            } elseif (strpos($before['content'], "\n") === false) {
                $phpcsFile->fixer->replaceToken($pointer - 1, ' ');
            }
        }

        if (!$afterValid) {
            if ($after['code'] !== T_WHITESPACE) {
                $phpcsFile->fixer->addContent($pointer, ' ');
            } elseif (strpos($after['content'], "\n") === false) {
                $phpcsFile->fixer->replaceToken($pointer + 1, ' ');
            } else {
                // whitespace runs into a newline: drop the trailing spaces, keep the line break
                $phpcsFile->fixer->replaceToken($pointer + 1, ltrim($after['content'], " \t"));
            }
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * @return list<int|string>
     */
    public function register(): array
    {
        return [
            T_DOUBLE_ARROW,
            T_MATCH_ARROW,
            T_FN_ARROW,
        ];
    }

}
