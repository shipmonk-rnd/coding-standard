<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Whitespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use function rtrim;
use function str_repeat;
use const T_CONST;
use const T_DOC_COMMENT_WHITESPACE;
use const T_FUNCTION;

final class DisallowOneLineDocCommentSniff implements Sniff
{

    public const CODE_ONE_LINE_COMMENT_USED = 'OneLineCommentUsed';

    /**
     * @return list<int>
     */
    public function register(): array
    {
        return [T_FUNCTION, T_CONST];
    }

    /**
     * @param int $pointer
     */
    public function process(
        File $phpcsFile,
        $pointer,
    ): void
    {
        $tokens = $phpcsFile->getTokens();

        $docCommentStartPointer = DocCommentHelper::findDocCommentOpenPointer($phpcsFile, $pointer);
        if ($docCommentStartPointer === null) {
            return;
        }

        $docCommentEndPointer = $tokens[$docCommentStartPointer]['comment_closer'];
        $lineDifference = $tokens[$docCommentEndPointer]['line'] - $tokens[$docCommentStartPointer]['line'];

        if ($lineDifference === 0) {
            $fix = $phpcsFile->addFixableError('Don\'t use one-line phpdoc.', $pointer, self::CODE_ONE_LINE_COMMENT_USED);

            if ($fix) {
                $indent = str_repeat(' ', $tokens[$docCommentStartPointer]['column'] - 1);

                $phpcsFile->fixer->beginChangeset();

                $nextPointer = $docCommentStartPointer + 1;

                if ($tokens[$nextPointer]['code'] === T_DOC_COMMENT_WHITESPACE) {
                    $phpcsFile->fixer->replaceToken($nextPointer, "\n{$indent} * ");
                } else {
                    $phpcsFile->fixer->addContentBefore($nextPointer, "\n{$indent} * ");
                }

                $prevPointer = $docCommentEndPointer - 1;

                if ($tokens[$prevPointer]['code'] === T_DOC_COMMENT_WHITESPACE) {
                    $phpcsFile->fixer->replaceToken($prevPointer, "\n{$indent} ");
                } else {
                    $phpcsFile->fixer->replaceToken($prevPointer, rtrim($tokens[$prevPointer]['content']));
                    $phpcsFile->fixer->addContentBefore($docCommentEndPointer, "\n{$indent} ");
                }

                $phpcsFile->fixer->endChangeset();
            }
        }
    }

}
