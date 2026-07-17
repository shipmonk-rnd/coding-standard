<?php declare(strict_types = 1);

namespace ShipMonkFmt;

use Throwable;
use function array_merge;

/**
 * Entry point. Outcomes (notes/04):
 *   MATCH  — output === input (the code was in the allowed set)
 *   REPAIR — output differs (snapped to the closest allowed form); per-statement
 *            Violations say where
 *   FATAL  — input returned byte-identical + error message (won't fix / internal);
 *            unsupported constructs inside statements degrade to per-statement
 *            verbatim passthrough + Violation instead of file-level fatal
 */
final class Formatter
{

    public function format(string $source): FormatResult
    {
        try {
            $parser = new Parser(Lexer::tokenize($source));
            $file = $parser->parseFile();
            $emitter = new Emitter($source);
            $file->render($emitter, RenderCtx::atLine(0));
            $output = $emitter->result();
            Verifier::verify($source, $output);

            return new FormatResult(
                $output,
                $output !== $source,
                null,
                array_merge($parser->violations, $emitter->violations()),
            );
        } catch (FatalError $e) {
            return new FormatResult($source, false, $e->getMessage());
        } catch (Throwable $e) {
            // "never corrupt, never crash the run" must not depend on templates
            // being exception-free (notes/50 §8)
            return new FormatResult($source, false, 'internal error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

}
