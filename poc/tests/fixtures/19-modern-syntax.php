<?php

class ModernSyntax
{

    private const string ROUTE = '/query/{id}';

    private const array HEADERS = ['origin'];

    public const PUBLIC = 'public';

    public private(set) int $counter = 0;

    public function visitor(): object
    {
        [, $second] = explode('/', self::ROUTE, 2);

        foreach ([[1, 'a'], [2, 'b']] as [, $label]) {
            $second .= $label;
        }

        return new class ($second) extends Base implements Visitor {

            public function __construct(
                private readonly string $tag,
            )
            {
            }

        };
    }

}
