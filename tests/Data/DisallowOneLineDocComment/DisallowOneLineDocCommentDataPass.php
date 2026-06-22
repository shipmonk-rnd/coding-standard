<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\DisallowOneLineDocComment;

final class DisallowOneLineDocCommentDataPass
{

    /**
     * @var string
     */
    public const FOO = 'foo';

    /**
     * @return void
     */
    public function multiLineDocComment(): void
    {
    }

    public function noDocComment(): void
    {
    }

    /**
     * Some description.
     *
     * @param string $foo
     * @return string
     */
    public function multiTagDocComment(string $foo): string
    {
        return $foo;
    }

}
