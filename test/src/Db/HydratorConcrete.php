<?php

namespace RealejoTest\Sdk\Db;

use DateTime;
use Realejo\Sdk\Db\AbstractHydrator;
use RealejoTest\Sdk\Enum\EnumConcrete;
use RealejoTest\Sdk\Enum\EnumFlaggedConcrete;
use stdClass;

/**
 * @property bool booleanKey
 * @property int intKey
 * @property stdClass jsonObjectKey
 * @property array jsonArrayKey
 * @property DateTime datetimeKey
 * @property EnumConcrete enum
 * @property EnumFlaggedConcrete enumFlagged
 */
class HydratorConcrete extends AbstractHydrator
{
    /**
     * Intencionalmente string_key não está no schema abaixo
     */
    protected static $schema = [
        'boolean_key' => ['type' => self::BOOLEAN],
        'int_key' => ['type' => self::INTEGER],
        'json_array_key' => ['type' => self::JSON_ARRAY],
        'json_object_key' => ['type' => self::JSON_OBJECT],
        'date_key' => ['type' => self::DATE],
        'datetime_key' => ['type' => self::DATETIME],
        'enum' => ['type' => self::ENUM, 'enum' => EnumConcrete::class],
        'enum_flagged' => ['type' => self::ENUM, 'enum' => EnumFlaggedConcrete::class],
    ];

    protected $jsonEncodeOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
}
