<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\Whitespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function floor;
use function str_repeat;
use function strlen;
use const T_BITWISE_OR;
use const T_CATCH;
use const T_CLOSE_PARENTHESIS;
use const T_OPEN_PARENTHESIS;
use const T_STRING;
use const T_VARIABLE;
use const T_WHITESPACE;

final class CatchSpacingSniff implements Sniff
{

    public const INVALID_SPACING = 'InvalidSpacing';

    private const INDENT_SIZE = 4;

    /**
     * @return list<(int|string)>
     */
    public function register(): array
    {
        return [
            T_CATCH,
        ];
    }

    /**
     * @param int $catchPointer
     */
    public function process(
        File $phpcsFile,
        $catchPointer
    ): void
    {
        $tokens = $phpcsFile->getTokens();
        $catchToken = $tokens[$catchPointer];

        $catchParenthesisOpenerPointer = $catchToken['parenthesis_opener'];
        $catchParenthesisCloserPointer = $catchToken['parenthesis_closer'];

        $closingParenthesisBeforeCatchPointer = TokenHelper::findPreviousEffective($phpcsFile, $catchPointer - 1);
        if ($closingParenthesisBeforeCatchPointer === null) {
            return; // invalid php file
        }

        $variablePointer = TokenHelper::findNext(
            $phpcsFile,
            T_VARIABLE,
            $catchParenthesisOpenerPointer,
            $catchParenthesisCloserPointer,
        );

        if ($variablePointer === null) {
            return; // Non-capturing catch is not supported by this sniff
        }

        $catchIndent = $tokens[$closingParenthesisBeforeCatchPointer - 1]['content'];
        $catchIndentSize = strlen($catchIndent);
        $expectedIndentBlocks = (int) floor($catchIndentSize / self::INDENT_SIZE) + 1;
        $expectedInnerIndent = str_repeat(' ', $expectedIndentBlocks * self::INDENT_SIZE);

        $multilineMode = $tokens[$catchParenthesisOpenerPointer + 1]['code'] === T_WHITESPACE && $tokens[$catchParenthesisOpenerPointer + 1]['content'] === "\n";
        $tokenPointer = $catchParenthesisOpenerPointer;

        do {
            if ($tokenPointer === $catchParenthesisCloserPointer) {
                break;
            }

            $token = $tokens[$tokenPointer];

            if ($token['code'] === T_OPEN_PARENTHESIS) {
                if ($multilineMode) {
                    if ($tokens[$tokenPointer + 1]['content'] !== "\n") {
                        $fix = $phpcsFile->addFixableError("Invalid catch format: expected newline after {$token['content']}", $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, "\n");
                        }
                    }

                    if ($tokens[$tokenPointer + 2]['content'] !== $expectedInnerIndent) {
                        $fix = $phpcsFile->addFixableError("Invalid catch indent, expected '{$expectedInnerIndent}'", $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 2, $expectedInnerIndent);
                        }
                    }

                } else {
                    if ($tokens[$tokenPointer + 1]['code'] === T_WHITESPACE) {
                        $fix = $phpcsFile->addFixableError("'Invalid catch format: expected no whitespace after {$token['content']}'", $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, '');
                        }
                    }
                }
            }

            if ($token['code'] === T_STRING) {
                if ($tokens[$tokenPointer + 1]['content'] !== ' ') {
                    $fix = $phpcsFile->addFixableError("Invalid catch format: expected single space after {$token['content']}", $tokenPointer, self::INVALID_SPACING);
                    if ($fix) {
                        $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, ' ');
                    }
                }
            }

            if ($token['code'] === T_BITWISE_OR) {
                if ($tokens[$tokenPointer - 1]['content'] !== ' ') {
                    $fix = $phpcsFile->addFixableError('Invalid catch format: expected single whitespace before |', $tokenPointer, self::INVALID_SPACING);
                    if ($fix) {
                        $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer - 1, ' ');
                    }
                }

                if ($multilineMode) {
                    if ($tokens[$tokenPointer + 1]['content'] !== "\n") {
                        $fix = $phpcsFile->addFixableError('Invalid catch format: expected newline after |', $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, "\n");
                        }
                    }

                    if ($tokens[$tokenPointer + 2]['content'] !== $expectedInnerIndent) {
                        $fix = $phpcsFile->addFixableError("Invalid catch indent, expected '{$expectedInnerIndent}'", $tokenPointer + 2, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 2, $expectedInnerIndent);
                        }
                    }

                } else {
                    if ($tokens[$tokenPointer + 1]['content'] !== ' ') {
                        $fix = $phpcsFile->addFixableError('Invalid catch format: expected single whitespace after |', $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, ' ');
                        }
                    }
                }
            }

            if ($token['code'] === T_VARIABLE) {
                if ($tokens[$tokenPointer - 1]['content'] !== ' ') {
                    $fix = $phpcsFile->addFixableError("Invalid catch format: expected single space before {$token['content']}", $tokenPointer, self::INVALID_SPACING);
                    if ($fix) {
                        $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer - 1, ' ');
                    }
                }

                if ($multilineMode) {
                    if ($tokens[$tokenPointer + 1]['content'] !== "\n") {
                        $fix = $phpcsFile->addFixableError("Invalid catch format: expected newline after {$token['content']}", $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, "\n");
                        }
                    }

                    if ($tokens[$tokenPointer + 2]['content'] !== $catchIndent) {
                        $fix = $phpcsFile->addFixableError("Invalid catch closing indent, expected '{$catchIndent}'", $tokenPointer + 2, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 2, $catchIndent);
                        }
                    }

                } else {
                    if ($tokens[$tokenPointer + 1]['code'] !== T_CLOSE_PARENTHESIS) {
                        $fix = $phpcsFile->addFixableError("Invalid catch format: no space expected after {$token['content']}", $tokenPointer, self::INVALID_SPACING);
                        if ($fix) {
                            $this->addOrReplaceWhitespaceToken($phpcsFile, $tokenPointer + 1, '');
                        }
                    }
                }
            }

            $tokenPointer++;
        } while (true);
    }

    private function addOrReplaceWhitespaceToken(
        File $phpcsFile,
        int $pointer,
        string $whitespaceContent
    ): void
    {
        $tokens = $phpcsFile->getTokens();

        $phpcsFile->fixer->beginChangeset();
        if ($tokens[$pointer]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer->replaceToken($pointer, $whitespaceContent);
        } else {
            if ($whitespaceContent === "\n") {
                $phpcsFile->fixer->addNewlineBefore($pointer);
            } else {
                $phpcsFile->fixer->addContentBefore($pointer, $whitespaceContent);
            }
        }
        $phpcsFile->fixer->endChangeset();
    }

}
