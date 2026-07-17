<?php

function collect(
    array &$visited,
    int ...$rest,
): void
{
}

function convert(
    #[FileArgument]
    string $inputFile,
    #[FormatOption] ?string $format = null,
): string
{
    $sql = 'WITH ancestors AS ('
        . ' SELECT parent FROM edges'
        . ') SELECT *';

    return match ($inputFile[0]) {
        '0', '1', '2', '3',
        '4', '5',
        '6' => $sql,
        default => strrev($sql),
    };
}
