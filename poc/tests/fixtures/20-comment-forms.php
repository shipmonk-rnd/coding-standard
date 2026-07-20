<?php declare(strict_types = 1);

namespace App;

// phpcs:disable Some.Pragma
final class CommentForms extends Base
// phpcs:enable Some.Pragma
{

    public function __construct(
        /**
         * @var list<int>
         */
        public array $quantities,
        // a plain leading comment row
        public string $sku,
    )
    {
    }

    public function classify(Kind $kind): string
    {
        return match ($kind) {
            // trivial cases
            Kind::A,
            Kind::B => 'flat',

            // grouped cases with per-item notes
            Kind::C, // first note
            Kind::D,

            Kind::E => 'grouped',
            default => 'other',
        };
    }

    public function run(): void
    {
        try {
            $this->classify(Kind::A);
        } catch (
            FooException |
            BarException $e
        ) {
            throw $e;
        }

        if (
            // explain the guard
            $this->ready
            && $this->enabled
            // trailing rationale
        ) {
            $this->go();
        }
    }

}
