<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use PhpToken;
use function rtrim;
use function strlen;
use function substr;

final class Lexer
{

    /**
     * @return list<SigToken> ending with a virtual EOF token that carries the trailing gap
     */
    public static function tokenize(string $source): array
    {
        $sig = [];
        $gap = '';
        $lastLine = 1;

        foreach (PhpToken::tokenize($source) as $token) {
            if ($token->id === T_WHITESPACE) {
                $gap .= $token->text;
                continue;
            }

            $text = $token->text;
            $lastLine = $token->line;

            if ($token->id === T_OPEN_TAG || $token->id === T_COMMENT) {
                // the open tag swallows one trailing whitespace char, and (in some PHP
                // versions) single-line comments swallow the newline — move that
                // whitespace where it belongs: into the following gap
                $trimmed = rtrim($text);
                $sig[] = new SigToken($token->id, $trimmed, $token->line, $gap);
                $gap = substr($text, strlen($trimmed));
                continue;
            }

            $sig[] = new SigToken($token->id, $text, $token->line, $gap);
            $gap = '';
        }

        $sig[] = new SigToken(SigToken::EOF, '', $lastLine, $gap);

        return $sig;
    }

}
