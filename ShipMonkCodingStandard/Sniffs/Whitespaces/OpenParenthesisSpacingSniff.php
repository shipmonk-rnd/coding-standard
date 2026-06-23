<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Whitespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use function ltrim;
use const T_CATCH;
use const T_ELSEIF;
use const T_IF;
use const T_WHITESPACE;

final class OpenParenthesisSpacingSniff implements Sniff
{

    private const WRONG_SPACING = 'WrongSpacing';

    /**
     * @param int $pointer
     */
    public function process(
        File $phpcsFile,
        $pointer,
    ): void
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$pointer]['parenthesis_opener'];
        $nextToken = $tokens[$opener + 1];

        if ($nextToken['code'] !== T_WHITESPACE || $nextToken['content'] === "\n") {
            return;
        }

        $fix = $phpcsFile->addFixableError('Expected 0 spaces after opening bracket', $opener + 1, self::WRONG_SPACING);

        if ($fix) {
            // strip the leading horizontal whitespace, preserving any newline (and its following indent token)
            $phpcsFile->fixer->replaceToken($opener + 1, ltrim($nextToken['content'], " \t"));
        }
    }

    /**
     * @return list<int>
     */
    public function register(): array
    {
        return [
            T_IF,
            T_ELSEIF,
            T_CATCH,
        ];
    }

}
