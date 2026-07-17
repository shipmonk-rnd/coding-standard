<?php declare(strict_types = 1);

namespace ShipMonkFmt;

/**
 * Entry point. Outcomes (notes/04):
 *   MATCH  — output === input (the code was in the allowed set)
 *   REPAIR — output differs (snapped to the closest allowed form)
 *   FATAL  — input returned byte-identical + error message (won't fix / unsupported)
 */
final class Formatter
{

    public function format(string $source): FormatResult
    {
        try {
            $file = (new Parser(Lexer::tokenize($source)))->parseFile();
            $output = $file->render(0);
            Verifier::verify($source, $output);

            return new FormatResult($output, $output !== $source, null);
        } catch (FatalError $e) {
            return new FormatResult($source, false, $e->getMessage());
        }
    }

}
