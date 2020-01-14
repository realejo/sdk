<?php

namespace RealejoTest\Sdk\Enum;

use Realejo\Sdk\Enum\Enum;

final class EnumConcrete extends Enum
{
    public const STRING1 = 'S';
    public const STRING2 = 'X';
    public const NUMERIC1 = 666;
    public const NUMERIC2 = 999;

    protected static $constDescription = [
        'S' => 'string1',
        'X' => ['string2', 'string with description'],
        666 => 'numeric1',
        999 => ['numeric2', 'numeric with description'],
    ];
}
