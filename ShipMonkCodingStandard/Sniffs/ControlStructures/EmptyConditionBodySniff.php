<?php declare(strict_types = 1);

namespace ShipMonkCodingStandard\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use function strtoupper;
use const T_ELSE;
use const T_ELSEIF;
use const T_IF;
use const T_WHITESPACE;

final class EmptyConditionBodySniff implements Sniff
{

    private const MISSING_COMMENT = 'MissingComment';

    /**
     * @return list<int>
     */
    public function register(): array
    {
        return [
            T_IF,
            T_ELSEIF,
            T_ELSE,
        ];
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

        if (!isset($tokens[$pointer]['scope_opener'], $tokens[$pointer]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$pointer]['scope_opener'];
        $scopeEnd = $tokens[$pointer]['scope_closer'];

        $firstContent = $phpcsFile->findNext(T_WHITESPACE, $scopeStart + 1, $scopeEnd, true);

        if ($firstContent === false) {
            $name = strtoupper($tokens[$pointer]['content']);
            $phpcsFile->addError("Empty {$name} body must have a comment to explain why it is empty", $scopeStart, self::MISSING_COMMENT);
        }
    }

}
