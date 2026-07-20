<?php declare(strict_types = 1);

namespace ShipMonk\Demo;

use Exception;
use LogicException;
use function count;
use const PHP_EOL;

/**
 * Demo class.
 *
 * @template T of object
 */
final class Demo extends Base implements Countable, Stringable
{

    private const LIMIT = 10; // inline note

    /**
     * @var list<string>
     */
    private array $items = [];

    public function __construct(
        private readonly string $name,
        private int $count = 0,
    )
    {
        parent::__construct($name);
    }

    public function register(): array
    {
        return [
            T_IF,
            T_ELSEIF,
        ];
    }

    abstract protected function template(?string $x): static;

}
